<?php

namespace App\Jobs;

use App\AI\Features\FollowUpGeneration\Contracts\FollowUpGenerator;
use App\Models\InterviewQuestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateFollowUpJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [5];

    public function __construct(
        public int $interviewQuestionId,
        public string $followUpKey,
    ) {
        $this->onQueue('high');
    }

    public function handle(FollowUpGenerator $followUpGenerator): void
    {
        $question = InterviewQuestion::query()
            ->with(['interview.position', 'followUps'])
            ->find($this->interviewQuestionId);

        if (! $question instanceof InterviewQuestion) {
            $this->markCacheCompleted(false);

            return;
        }

        $position = $question->interview?->position;

        if ($position === null || ! $position->follow_up_enabled) {
            $this->markCacheCompleted(false);

            return;
        }

        $rootQuestionId = $question->resolveRootQuestionId();
        $existingFollowUpCount = InterviewQuestion::query()
            ->where('parent_question_id', $rootQuestionId)
            ->count();

        if ($existingFollowUpCount >= $position->max_follow_ups_per_question) {
            $this->markCacheCompleted(false);

            return;
        }

        if ($question->candidate_answer === null || trim($question->candidate_answer) === '') {
            $this->markCacheCompleted(false);

            return;
        }

        $rootQuestion = $question->isFollowUp()
            ? InterviewQuestion::query()
                ->with('followUps')
                ->find($rootQuestionId)
            : $question;

        if (! $rootQuestion instanceof InterviewQuestion) {
            $this->markCacheCompleted(false);

            return;
        }

        $result = $followUpGenerator->generate($rootQuestion, $position->follow_up_min_score);

        if (! $result->needsFollowUp || $result->followUpQuestion === null) {
            $this->markCacheCompleted(false);

            return;
        }

        $followUp = InterviewQuestion::query()->create([
            'interview_id' => $question->interview_id,
            'question_id' => null,
            'parent_question_id' => $rootQuestionId,
            'question_text' => $result->followUpQuestion,
            'evaluation_instructions_snapshot' => $rootQuestion->evaluation_instructions_snapshot,
            'answer_mode' => $rootQuestion->answer_mode,
            'sort_order' => $rootQuestion->sort_order,
        ]);

        Cache::put("follow_up:{$this->followUpKey}", [
            'status' => 'completed',
            'needs_follow_up' => true,
            'follow_up' => [
                'id' => $followUp->id,
                'question_text' => $followUp->question_text,
                'answer_mode' => $followUp->answer_mode->value,
            ],
        ], now()->addMinutes(10));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateFollowUpJob failed', [
            'interview_question_id' => $this->interviewQuestionId,
            'follow_up_key' => $this->followUpKey,
            'exception' => $exception->getMessage(),
        ]);

        Cache::put("follow_up:{$this->followUpKey}", [
            'status' => 'failed',
            'needs_follow_up' => false,
        ], now()->addMinutes(10));
    }

    private function markCacheCompleted(bool $needsFollowUp): void
    {
        Cache::put("follow_up:{$this->followUpKey}", [
            'status' => 'completed',
            'needs_follow_up' => $needsFollowUp,
        ], now()->addMinutes(10));
    }
}
