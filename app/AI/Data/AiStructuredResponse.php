<?php

namespace App\AI\Data;

final readonly class AiStructuredResponse
{
    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        public array $content,
        public string $rawResponse,
    ) {}
}
