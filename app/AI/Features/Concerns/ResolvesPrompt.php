<?php

namespace App\AI\Features\Concerns;

use App\Models\AiPrompt;
use RuntimeException;

trait ResolvesPrompt
{
    /**
     * @param  array<string, string>  $placeholders
     */
    protected function resolvePrompt(string $feature, string $type, array $placeholders = []): string
    {
        $resolved = AiPrompt::resolve($feature, $type, $placeholders);

        if (is_string($resolved) && $resolved !== '') {
            return $resolved;
        }

        throw new RuntimeException("AI prompt not found: feature={$feature}, type={$type}. Run the AiPromptSeeder.");
    }
}
