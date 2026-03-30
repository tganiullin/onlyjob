<?php

namespace App\Services;

use App\AI\Features\InterviewReview\Contracts\InterviewReviewer;
use App\AI\Features\InterviewReview\Data\InterviewQuestionReviewResult;
use App\AI\Features\InterviewReview\Data\InterviewReviewResult;
use App\Enums\InterviewStatus;
use App\Models\Interview;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InterviewReviewService
{
    public function __construct(
        public InterviewReviewer $interviewReviewer,
    ) {}

    public function reviewAndApply(Interview $interview): void
    {
        $reviewResult = $this->interviewReviewer->review($interview);

        DB::transaction(function () use ($interview, $reviewResult): void {
            $freshInterview = Interview::query()
                ->with(['position', 'interviewQuestions'])
                ->findOrFail($interview->id);

            $this->applyQuestionResults($freshInterview, $reviewResult);

            $freshInterview->forceFill([
                'summary' => $reviewResult->summary,
            ])->saveQuietly();

            $freshInterview->syncScoreFromAnswers();
            $freshInterview->syncAdequacyScoreFromAnswers();
            $freshInterview->refresh();
            $this->syncFinalStatus($freshInterview);
        });
    }

    private function applyQuestionResults(Interview $interview, InterviewReviewResult $reviewResult): void
    {
        $expectedQuestionIds = $interview->interviewQuestions
            ->whereNull('parent_question_id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $questionResultsById = collect($reviewResult->questionResults)
            ->keyBy(static fn (InterviewQuestionReviewResult $result): int => $result->interviewQuestionId);

        $receivedQuestionIds = $questionResultsById->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($expectedQuestionIds !== $receivedQuestionIds) {
            throw new InvalidArgumentException('AI review result does not match interview question ids.');
        }

        foreach ($interview->interviewQuestions->whereNull('parent_question_id') as $interviewQuestion) {
            $questionResult = $questionResultsById->get($interviewQuestion->id);

            if (! $questionResult instanceof InterviewQuestionReviewResult) {
                continue;
            }

            $interviewQuestion->forceFill([
                'answer_score' => $questionResult->answerScore,
                'adequacy_score' => $questionResult->adequacyScore,
                'ai_comment' => $questionResult->aiComment,
            ])->saveQuietly();
        }
    }

    private function syncFinalStatus(Interview $interview): void
    {
        if (! in_array($interview->status, [
            InterviewStatus::Completed,
            InterviewStatus::QueuedForReview,
            InterviewStatus::Reviewing,
        ], true) || $interview->score === null) {
            return;
        }

        $minimumScore = $interview->position?->minimum_score;

        if (! is_numeric($minimumScore)) {
            return;
        }

        $nextStatus = (float) $interview->score >= (float) $minimumScore
            ? InterviewStatus::ReviewedPassed
            : InterviewStatus::ReviewedFailed;

        $interview->forceFill([
            'status' => $nextStatus,
        ])->saveQuietly();
    }
}
