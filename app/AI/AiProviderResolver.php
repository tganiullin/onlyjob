<?php

namespace App\AI;

use App\AI\Contracts\AiProvider;
use InvalidArgumentException;
use RuntimeException;

final class AiProviderResolver
{
    public function resolveForFeature(string $feature): AiProvider
    {
        $provider = config("ai.features.{$feature}.provider");

        if (! is_string($provider) || $provider === '') {
            $provider = config('ai.default_provider');
        }

        if (! is_string($provider) || $provider === '') {
            throw new InvalidArgumentException('AI provider is not configured.');
        }

        $providerClass = config("ai.providers.{$provider}");

        if (! is_string($providerClass) || $providerClass === '') {
            throw new RuntimeException(sprintf('AI provider class is not configured for "%s".', $provider));
        }

        $resolvedProvider = app($providerClass);

        if (! $resolvedProvider instanceof AiProvider) {
            throw new RuntimeException(
                sprintf('AI provider "%s" must implement %s.', $providerClass, AiProvider::class),
            );
        }

        return $resolvedProvider;
    }
}
