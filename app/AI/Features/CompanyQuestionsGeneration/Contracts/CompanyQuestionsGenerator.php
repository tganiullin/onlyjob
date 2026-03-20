<?php

namespace App\AI\Features\CompanyQuestionsGeneration\Contracts;

interface CompanyQuestionsGenerator
{
    /**
     * @param  array<string, mixed>  $context
     * @return list<array{question: string, answer: string}>
     */
    public function generate(array $context): array;
}
