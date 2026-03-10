<?php

namespace App\AI\Features\AudioInterview\Contracts;

interface AudioInterviewOrchestrator
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function evaluate(array $context): array;
}
