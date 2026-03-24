<?php

namespace App\AI\Features\FollowUpEvaluation\Data;

use InvalidArgumentException;

final readonly class FollowUpEvaluationResult
{
    public function __construct(
        public bool $needsFollowUp,
        public float $scoreEstimate,
        public ?string $followUpQuestion,
    ) {}

    public static function noFollowUp(float $scoreEstimate): self
    {
        return new self(
            needsFollowUp: false,
            scoreEstimate: $scoreEstimate,
            followUpQuestion: null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $needsFollowUp = $payload['needs_follow_up'] ?? false;
        $scoreEstimate = $payload['score_estimate'] ?? null;
        $followUpQuestion = $payload['follow_up_question'] ?? null;

        if (! is_numeric($scoreEstimate)) {
            throw new InvalidArgumentException('Score estimate must be numeric.');
        }

        $scoreEstimate = max(1.0, min(10.0, round((float) $scoreEstimate, 2)));

        if ($needsFollowUp && (! is_string($followUpQuestion) || trim($followUpQuestion) === '')) {
            return self::noFollowUp($scoreEstimate);
        }

        return new self(
            needsFollowUp: (bool) $needsFollowUp,
            scoreEstimate: $scoreEstimate,
            followUpQuestion: is_string($followUpQuestion) && trim($followUpQuestion) !== ''
                ? trim($followUpQuestion)
                : null,
        );
    }
}
