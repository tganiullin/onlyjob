<?php

namespace App\AI\Features\InterviewReview\Data;

use InvalidArgumentException;

final readonly class InterviewReviewResult
{
    /**
     * @param  list<InterviewQuestionReviewResult>  $questionResults
     */
    public function __construct(
        public string $summary,
        public array $questionResults,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $summary = $payload['summary'] ?? null;
        $questions = $payload['questions'] ?? null;

        if (! is_string($summary) || trim($summary) === '') {
            throw new InvalidArgumentException('Interview summary is required.');
        }

        if (! is_array($questions)) {
            throw new InvalidArgumentException('Interview review questions must be an array.');
        }

        $questionResults = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                throw new InvalidArgumentException('Each interview review question must be an object.');
            }

            $questionResults[] = InterviewQuestionReviewResult::fromArray($question);
        }

        return new self(
            summary: trim($summary),
            questionResults: $questionResults,
        );
    }
}
