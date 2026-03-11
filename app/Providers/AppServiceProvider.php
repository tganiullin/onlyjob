<?php

namespace App\Providers;

use App\AI\Features\InterviewReview\AiInterviewReviewer;
use App\AI\Features\InterviewReview\Contracts\InterviewReviewer;
use App\AI\Features\QuestionGeneration\AiQuestionGenerator;
use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\AI\Features\SpeechToText\Contracts\VoiceActivityDetector;
use App\AI\Features\SpeechToText\FfmpegVoiceActivityDetector;
use App\AI\Features\SpeechToText\VadSpeechTranscriber;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ApiKeyIsMissing;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InterviewReviewer::class, AiInterviewReviewer::class);
        $this->app->bind(QuestionGenerator::class, AiQuestionGenerator::class);
        $this->app->bind(VoiceActivityDetector::class, FfmpegVoiceActivityDetector::class);
        $this->app->bind(SpeechTranscriber::class, VadSpeechTranscriber::class);
        $this->extendOpenAiClientWithProxySupport();
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
    }

    private function extendOpenAiClientWithProxySupport(): void
    {
        $this->app->extend(ClientContract::class, function (ClientContract $client): ClientContract {
            $proxy = trim((string) config('openai.proxy', ''));

            if ($proxy === '') {
                return $client;
            }

            $apiKey = config('openai.api_key');
            $organization = config('openai.organization');
            $project = config('openai.project');
            $baseUri = config('openai.base_uri');

            if (! is_string($apiKey) || ($organization !== null && ! is_string($organization))) {
                throw ApiKeyIsMissing::create();
            }

            $httpClientOptions = [
                'timeout' => config('openai.request_timeout', 30),
                'proxy' => $proxy,
            ];

            $clientFactory = \OpenAI::factory()
                ->withApiKey($apiKey)
                ->withOrganization($organization)
                ->withHttpClient(new GuzzleClient($httpClientOptions));

            if (is_string($project)) {
                $clientFactory->withProject($project);
            }

            if (is_string($baseUri)) {
                $clientFactory->withBaseUri($baseUri);
            }

            return $clientFactory->make();
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
