<?php

namespace App\AI\Features\Concerns;

use App\Models\AiPrompt;

trait ResolvesPrompt
{
    /**
     * Resolve a prompt from DB with placeholder substitution, falling back to the given default.
     *
     * @param  array<string, string>  $placeholders
     */
    protected function resolvePrompt(string $feature, string $type, string $default, array $placeholders = []): string
    {
        $resolved = AiPrompt::resolve($feature, $type, $placeholders);

        if (is_string($resolved) && $resolved !== '') {
            return $resolved;
        }

        return AiPrompt::replacePlaceholders($default, $placeholders);
    }
}
