<?php

namespace App\Services;

use App\AI\Features\FollowUpEvaluation\Contracts\FollowUpEvaluator;
use App\AI\Features\FollowUpEvaluation\Data\FollowUpEvaluationResult;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use App\Models\Position;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FollowUpService
{
    public function __construct(
        private FollowUpEvaluator $evaluator,
    ) {}

    public function evaluateAndCreateFollowUp(
        Interview $interview,
        InterviewQuestion $answeredQuestion,
    ): ?InterviewQuestion {
        $interview->loadMissing('position');

        $position = $interview->position;

        if (! $position instanceof Position || ! $position->follow_up_enabled) {
            return null;
        }

        if ($this->hasReachedFollowUpLimit($answeredQuestion, $position)) {
            return null;
        }

        if ($this->isSkippedAnswer($answeredQuestion)) {
            return null;
        }

        try {
            $result = $this->evaluator->evaluate($answeredQuestion, $position);
        } catch (Throwable $exception) {
            Log::warning('Follow-up evaluation failed, skipping.', [
                'interview_id' => $interview->id,
                'interview_question_id' => $answeredQuestion->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $result->needsFollowUp || $result->followUpQuestion === null) {
            return null;
        }

        return $this->createFollowUpQuestion($interview, $answeredQuestion, $result);
    }

    private function hasReachedFollowUpLimit(InterviewQuestion $question, Position $position): bool
    {
        $rootQuestion = $this->resolveRootQuestion($question);

        $existingFollowUps = InterviewQuestion::query()
            ->where('interview_id', $question->interview_id)
            ->where('is_follow_up', true)
            ->where(function ($query) use ($rootQuestion): void {
                $query
                    ->where('parent_interview_question_id', $rootQuestion->id)
                    ->orWhere(function ($nested) use ($rootQuestion): void {
                        $nested->whereIn(
                            'parent_interview_question_id',
                            InterviewQuestion::query()
                                ->select('id')
                                ->where('parent_interview_question_id', $rootQuestion->id),
                        );
                    });
            })
            ->count();

        return $existingFollowUps >= $position->max_follow_ups_per_question;
    }

    private function resolveRootQuestion(InterviewQuestion $question): InterviewQuestion
    {
        $current = $question;
        $depth = 0;

        while ($current->is_follow_up && $current->parent_interview_question_id !== null && $depth < 10) {
            $parent = InterviewQuestion::find($current->parent_interview_question_id);

            if (! $parent instanceof InterviewQuestion) {
                break;
            }

            $current = $parent;
            $depth++;
        }

        return $current;
    }

    private function isSkippedAnswer(InterviewQuestion $question): bool
    {
        $answer = trim((string) $question->candidate_answer);

        return $answer === '' || mb_strtolower($answer) === 'не знаю ответ';
    }

    private function createFollowUpQuestion(
        Interview $interview,
        InterviewQuestion $parentQuestion,
        FollowUpEvaluationResult $result,
    ): InterviewQuestion {
        return $interview->interviewQuestions()->create([
            'question_text' => $result->followUpQuestion,
            'evaluation_instructions_snapshot' => $parentQuestion->evaluation_instructions_snapshot,
            'sort_order' => $parentQuestion->sort_order,
            'is_follow_up' => true,
            'parent_interview_question_id' => $parentQuestion->id,
        ]);
    }
}
