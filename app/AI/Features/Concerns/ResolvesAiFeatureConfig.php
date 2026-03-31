<?php

namespace App\AI\Features\Concerns;

trait ResolvesAiFeatureConfig
{
    protected function resolveFeatureModel(string $feature): ?string
    {
        $model = config("ai.features.{$feature}.model");

        if (! is_string($model) || $model === '') {
            return null;
        }

        return $model;
    }

    protected function resolveFeatureTemperature(string $feature): ?float
    {
        $temperature = config("ai.features.{$feature}.temperature");

        if (! is_numeric($temperature)) {
            return null;
        }

        return (float) $temperature;
    }

    protected function resolveFeatureMaxTokens(string $feature): ?int
    {
        $maxTokens = config("ai.features.{$feature}.max_tokens");

        if (! is_numeric($maxTokens)) {
            return null;
        }

        return max(1, (int) $maxTokens);
    }

    protected function resolveOutputLanguage(string $feature): string
    {
        $outputLanguage = config("ai.features.{$feature}.output_language");

        if (! is_string($outputLanguage) || trim($outputLanguage) === '') {
            return 'Russian';
        }

        return match (strtolower(trim($outputLanguage))) {
            'ru', 'russian', 'русский', 'same_as_input' => 'Russian',
            default => trim($outputLanguage),
        };
    }
}
