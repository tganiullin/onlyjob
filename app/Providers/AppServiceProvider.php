<?php

namespace App\Providers;

use App\AI\Features\InterviewReview\AiInterviewReviewer;
use App\AI\Features\InterviewReview\Contracts\InterviewReviewer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InterviewReviewer::class, AiInterviewReviewer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
