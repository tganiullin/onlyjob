<?php

namespace App\AI\Features\InterviewReview\Data;

use InvalidArgumentException;

final readonly class InterviewQuestionReviewResult
{
    public function __construct(
        public int $interviewQuestionId,
        public float $answerScore,
        public string $aiComment,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $questionId = $payload['interview_question_id'] ?? null;
        $answerScore = $payload['answer_score'] ?? null;
        $aiComment = $payload['ai_comment'] ?? null;

        if (! is_int($questionId) && ! is_string($questionId)) {
            throw new InvalidArgumentException('Interview question id must be int|string.');
        }

        if (! is_numeric($answerScore)) {
            throw new InvalidArgumentException('Answer score must be numeric.');
        }

        if (! is_string($aiComment) || trim($aiComment) === '') {
            throw new InvalidArgumentException('AI comment is required.');
        }

        return new self(
            interviewQuestionId: (int) $questionId,
            answerScore: max(1, min(10, round((float) $answerScore, 2))),
            aiComment: trim($aiComment),
        );
    }
}
