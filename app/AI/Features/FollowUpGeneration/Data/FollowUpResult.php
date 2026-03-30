<?php

namespace App\AI\Features\FollowUpGeneration\Data;

use InvalidArgumentException;

final readonly class FollowUpResult
{
    public function __construct(
        public bool $needsFollowUp,
        public ?string $followUpQuestion,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $needsFollowUp = $payload['needs_follow_up'] ?? false;

        if (! is_bool($needsFollowUp)) {
            throw new InvalidArgumentException('needs_follow_up must be a boolean.');
        }

        $followUpQuestion = $payload['follow_up_question'] ?? null;

        if ($needsFollowUp && (! is_string($followUpQuestion) || trim($followUpQuestion) === '')) {
            throw new InvalidArgumentException('follow_up_question is required when needs_follow_up is true.');
        }

        return new self(
            needsFollowUp: $needsFollowUp,
            followUpQuestion: $needsFollowUp && is_string($followUpQuestion) ? trim($followUpQuestion) : null,
        );
    }
}
