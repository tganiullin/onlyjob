<?php

namespace App\AI\Exceptions;

use RuntimeException;
use Throwable;

class AiProviderException extends RuntimeException
{
    public static function requestFailed(string $provider, Throwable $previous): self
    {
        return new self(
            sprintf('AI provider "%s" request failed: %s', $provider, $previous->getMessage()),
            previous: $previous,
        );
    }
}
