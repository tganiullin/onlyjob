<?php

namespace App\AI\Data;

final readonly class AiRequest
{
    /**
     * @param  array<string, mixed>|null  $jsonSchema
     */
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public ?array $jsonSchema = null,
        public ?string $schemaName = null,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
    ) {}
}
