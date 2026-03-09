<?php

namespace App\AI\Contracts;

use App\AI\Data\AiRequest;
use App\AI\Data\AiStructuredResponse;

interface AiProvider
{
    public function generateStructured(AiRequest $request): AiStructuredResponse;
}
