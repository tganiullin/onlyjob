<?php

namespace App\Providers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\ServiceProvider;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ApiKeyIsMissing;

class OpenAiServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend(ClientContract::class, function (ClientContract $client): ClientContract {
            $proxy = trim((string) config('openai.proxy', ''));

            if ($proxy === '') {
                return $client;
            }

            return $this->createProxiedClient($proxy);
        });
    }

    private function createProxiedClient(string $proxy): ClientContract
    {
        $apiKey = config('openai.api_key');
        $organization = config('openai.organization');
        $project = config('openai.project');
        $baseUri = config('openai.base_uri');

        if (! is_string($apiKey) || ($organization !== null && ! is_string($organization))) {
            throw ApiKeyIsMissing::create();
        }

        $clientFactory = \OpenAI::factory()
            ->withApiKey($apiKey)
            ->withOrganization($organization)
            ->withHttpClient(new GuzzleClient([
                'timeout' => config('openai.request_timeout', 30),
                'proxy' => $proxy,
            ]));

        if (is_string($project)) {
            $clientFactory->withProject($project);
        }

        if (is_string($baseUri)) {
            $clientFactory->withBaseUri($baseUri);
        }

        return $clientFactory->make();
    }
}
