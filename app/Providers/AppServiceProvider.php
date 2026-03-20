<?php

namespace App\Providers;

use App\AI\Features\CompanyQuestionsGeneration\AiCompanyQuestionsGenerator;
use App\AI\Features\CompanyQuestionsGeneration\Contracts\CompanyQuestionsGenerator;
use App\AI\Features\InterviewReview\AiInterviewReviewer;
use App\AI\Features\InterviewReview\Contracts\InterviewReviewer;
use App\AI\Features\QuestionGeneration\AiQuestionGenerator;
use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\AI\Features\SpeechToText\Contracts\VoiceActivityDetector;
use App\AI\Features\SpeechToText\FfmpegVoiceActivityDetector;
use App\AI\Features\SpeechToText\VadSpeechTranscriber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InterviewReviewer::class, AiInterviewReviewer::class);
        $this->app->bind(CompanyQuestionsGenerator::class, AiCompanyQuestionsGenerator::class);
        $this->app->bind(QuestionGenerator::class, AiQuestionGenerator::class);
        $this->app->bind(VoiceActivityDetector::class, FfmpegVoiceActivityDetector::class);
        $this->app->bind(SpeechTranscriber::class, VadSpeechTranscriber::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-position-start', function (Request $request): Limit {
            return Limit::perMinute(5)->by(sprintf(
                'public-position-start:%s:%s',
                (string) $request->ip(),
                $this->resolveRouteSegmentKey($request, 'token'),
            ));
        });

        RateLimiter::for('public-interview-transcribe', function (Request $request): Limit {
            return Limit::perMinute(10)->by(sprintf(
                'public-interview-transcribe:%s:%s',
                (string) $request->ip(),
                $this->resolveRouteSegmentKey($request, 'interview'),
            ));
        });

        RateLimiter::for('public-interview-answer', function (Request $request): Limit {
            return Limit::perMinute(30)->by(sprintf(
                'public-interview-answer:%s:%s',
                (string) $request->ip(),
                $this->resolveRouteSegmentKey($request, 'interview'),
            ));
        });

        RateLimiter::for('public-interview-confirmation-status', function (Request $request): Limit {
            return Limit::perMinute(30)->by(sprintf(
                'public-interview-confirmation-status:%s:%s',
                (string) $request->ip(),
                $this->resolveRouteSegmentKey($request, 'statusToken'),
            ));
        });

        RateLimiter::for('telegram-webhook', function (Request $request): Limit {
            return Limit::perMinute(30)->by(sprintf(
                'telegram-webhook:%s',
                (string) $request->ip(),
            ));
        });
    }

    private function resolveRouteSegmentKey(Request $request, string $segment): string
    {
        $segmentValue = $request->route($segment);

        if (is_object($segmentValue) && method_exists($segmentValue, 'getKey')) {
            return (string) $segmentValue->getKey();
        }

        if ($segmentValue === null || $segmentValue === '') {
            return 'unknown';
        }

        return (string) $segmentValue;
    }
}
