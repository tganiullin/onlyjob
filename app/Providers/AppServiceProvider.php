<?php

namespace App\Providers;

use App\AI\Features\InterviewReview\AiInterviewReviewer;
use App\AI\Features\InterviewReview\Contracts\InterviewReviewer;
use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\AI\Features\SpeechToText\OpenAiSpeechTranscriber;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InterviewReviewer::class, AiInterviewReviewer::class);
        $this->app->bind(SpeechTranscriber::class, OpenAiSpeechTranscriber::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
