<?php

namespace App\Http\Controllers;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\Enums\InterviewStatus;
use App\Http\Requests\StorePublicInterviewAnswerRequest;
use App\Http\Requests\StorePublicInterviewFeedbackRequest;
use App\Http\Requests\TranscribePublicInterviewAudioRequest;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use App\Models\Position;
use App\Models\PositionCompanyQuestion;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicInterviewRunController extends Controller
{
    private const COMPLETION_MESSAGE = 'Спасибо! Вы успешно завершили первый этап интервью.';

    public function run(Interview $interview): View
    {
        $this->abortIfInterviewNotAccessible($interview);

        $questions = $interview->interviewQuestions()
            ->orderBy('sort_order')
            ->get(['id', 'question_text', 'candidate_answer'])
            ->map(static fn (InterviewQuestion $question): array => [
                'id' => $question->id,
                'text' => $question->question_text,
                'candidate_answer' => $question->candidate_answer,
            ])
            ->all();

        $answerTimeSeconds = $interview->position?->answer_time_seconds;
        $companyQuestions = $interview->position?->companyQuestions()?->get(['id', 'question', 'answer'])
            ->map(static fn (PositionCompanyQuestion $question): array => [
                'id' => $question->id,
                'question' => $question->question,
                'answer' => $question->answer,
            ])
            ->all() ?? [];

        if ($answerTimeSeconds instanceof BackedEnum) {
            $answerTimeSeconds = $answerTimeSeconds->value;
        }

        if (! is_numeric($answerTimeSeconds) || (int) $answerTimeSeconds <= 0) {
            $answerTimeSeconds = 120;
        }

        return view('public-interviews.run', [
            'interview' => $interview,
            'position' => $interview->position,
            'questions' => $questions,
            'companyQuestions' => $companyQuestions,
            'answerTimeSeconds' => (int) $answerTimeSeconds,
            'interviewCompleted' => $this->isInterviewTerminal($interview),
            'completionMessage' => self::COMPLETION_MESSAGE,
            'candidateFeedbackRating' => $interview->candidate_feedback_rating,
        ]);
    }

    public function answer(
        StorePublicInterviewAnswerRequest $request,
        Interview $interview,
        InterviewQuestion $interviewQuestion,
    ): JsonResponse {
        $this->abortIfInterviewNotAccessible($interview);

        if ($interviewQuestion->interview_id !== $interview->id) {
            abort(404);
        }

        if ($this->isInterviewTerminal($interview)) {
            return response()->json([
                'completed' => true,
                'message' => self::COMPLETION_MESSAGE,
            ]);
        }

        $expectedQuestion = $this->resolveExpectedQuestion($interview);

        if (! $expectedQuestion instanceof InterviewQuestion) {
            $this->markInterviewAsCompleted($interview);

            return response()->json([
                'completed' => true,
                'message' => self::COMPLETION_MESSAGE,
            ]);
        }

        if ($interviewQuestion->id !== $expectedQuestion->id) {
            return response()->json([
                'completed' => false,
                'message' => 'Please answer the current question before moving to the next one.',
                'next_question' => [
                    'id' => $expectedQuestion->id,
                    'text' => $expectedQuestion->question_text,
                ],
            ], 409);
        }

        $expectedQuestion->forceFill([
            'candidate_answer' => $request->validated('candidate_answer'),
        ])->save();

        $this->markInterviewAsInProgress($interview);

        $nextQuestion = $this->resolveExpectedQuestion($interview);

        if ($nextQuestion instanceof InterviewQuestion) {
            return response()->json([
                'completed' => false,
                'next_question' => [
                    'id' => $nextQuestion->id,
                    'text' => $nextQuestion->question_text,
                ],
            ]);
        }

        $this->markInterviewAsCompleted($interview);

        return response()->json([
            'completed' => true,
            'message' => self::COMPLETION_MESSAGE,
        ]);
    }

    public function feedback(
        StorePublicInterviewFeedbackRequest $request,
        Interview $interview,
    ): JsonResponse {
        $this->abortIfInterviewNotAccessible($interview);

        if (! $this->isInterviewTerminal($interview)) {
            return response()->json([
                'message' => 'Feedback is available only after completing the interview.',
            ], 409);
        }

        $rating = (int) $request->validated('candidate_feedback_rating');

        $interview->forceFill([
            'candidate_feedback_rating' => $rating,
        ])->save();

        return response()->json([
            'saved' => true,
            'candidate_feedback_rating' => $rating,
        ]);
    }

    public function transcribe(
        TranscribePublicInterviewAudioRequest $request,
        Interview $interview,
        SpeechTranscriber $speechTranscriber,
    ): JsonResponse {
        $this->abortIfInterviewNotAccessible($interview);

        if ($this->isInterviewTerminal($interview)) {
            return response()->json([
                'text' => '',
            ]);
        }

        /** @var \Illuminate\Http\UploadedFile $audioFile */
        $audioFile = $request->file('audio');

        return response()->json([
            'text' => $speechTranscriber->transcribe(
                $audioFile,
                (string) $request->validated('language'),
            ),
        ]);
    }

    private function abortIfInterviewNotAccessible(Interview $interview): void
    {
        $sessionInterviewId = session('public_interview_id');

        if (! is_numeric($sessionInterviewId) || (int) $sessionInterviewId !== $interview->id) {
            abort(403);
        }

        $interview->loadMissing('position');

        if (! $interview->position instanceof Position) {
            abort(404);
        }

        if ($interview->telegram_confirmed_at === null) {
            abort(403);
        }
    }

    private function resolveExpectedQuestion(Interview $interview): ?InterviewQuestion
    {
        return $interview->interviewQuestions()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('candidate_answer')
                    ->orWhere('candidate_answer', '');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function isInterviewTerminal(Interview $interview): bool
    {
        return in_array($interview->status, [
            InterviewStatus::Completed,
            InterviewStatus::QueuedForReview,
            InterviewStatus::Reviewing,
            InterviewStatus::ReviewedPassed,
            InterviewStatus::ReviewedFailed,
            InterviewStatus::ReviewFailed,
        ], true);
    }

    private function markInterviewAsInProgress(Interview $interview): void
    {
        if ($interview->status === InterviewStatus::InProgress) {
            return;
        }

        if ($interview->status !== InterviewStatus::PendingInterview) {
            return;
        }

        $interview->forceFill([
            'status' => InterviewStatus::InProgress,
            'started_at' => $interview->started_at ?? now(),
        ])->save();
    }

    private function markInterviewAsCompleted(Interview $interview): void
    {
        if ($interview->status === InterviewStatus::Completed && $interview->completed_at !== null) {
            return;
        }

        if (! in_array($interview->status, [
            InterviewStatus::PendingInterview,
            InterviewStatus::InProgress,
            InterviewStatus::Completed,
        ], true)) {
            return;
        }

        $interview->forceFill([
            'status' => InterviewStatus::Completed,
            'started_at' => $interview->started_at ?? now(),
            'completed_at' => $interview->completed_at ?? now(),
        ])->save();
    }
}
