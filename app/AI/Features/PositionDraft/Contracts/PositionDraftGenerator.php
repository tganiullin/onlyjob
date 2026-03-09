<?php

namespace App\AI\Features\PositionDraft\Contracts;

interface PositionDraftGenerator
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array;
}
