<?php

namespace App\AI\Features\QuestionGeneration\Contracts;

interface QuestionGenerator
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $context): array;
}
