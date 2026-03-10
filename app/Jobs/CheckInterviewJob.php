<?php

namespace App\Jobs;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Services\InterviewReviewService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckInterviewJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct(
        public int $interviewId,
    ) {}

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

        if ($interview->status !== InterviewStatus::Completed) {
            return;
        }

        if ($interview->interviewQuestions->isEmpty()) {
            return;
        }

        $interviewReviewService->reviewAndApply($interview);
    }
}
