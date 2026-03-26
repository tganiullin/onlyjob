<?php

namespace App\Jobs;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Services\InterviewReviewService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckInterviewJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [30];

    public int $uniqueFor = 600;

    public function __construct(
        public int $interviewId,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->interviewId;
    }

    public function handle(InterviewReviewService $interviewReviewService): void
    {
        $interview = Interview::query()
            ->with(['position', 'interviewQuestions'])
            ->find($this->interviewId);

        if (! $interview instanceof Interview) {
            return;
        }

        if (! in_array($interview->status, [
            InterviewStatus::Completed,
            InterviewStatus::QueuedForReview,
            InterviewStatus::Reviewing,
        ], true)) {
            return;
        }

        if ($interview->interviewQuestions->isEmpty()) {
            return;
        }

        $interview->forceFill([
            'status' => InterviewStatus::Reviewing,
        ])->saveQuietly();

        $interviewReviewService->reviewAndApply($interview);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CheckInterviewJob failed', [
            'interview_id' => $this->interviewId,
            'exception' => $exception->getMessage(),
        ]);

        Interview::query()
            ->where('id', $this->interviewId)
            ->where('status', InterviewStatus::Reviewing)
            ->update(['status' => InterviewStatus::ReviewFailed]);
    }
}
