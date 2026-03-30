<?php

namespace App\AI\Features\InterviewReview\Data;

use InvalidArgumentException;

final readonly class InterviewQuestionReviewResult
{
    /**
     * @param  list<self>  $followUpResults
     */
    public function __construct(
        public int $interviewQuestionId,
        public float $answerScore,
        public float $adequacyScore,
        public string $aiComment,
        public array $followUpResults = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload, bool $parseFollowUps = true): self
    {
        $questionId = $payload['interview_question_id'] ?? null;
        $answerScore = $payload['answer_score'] ?? null;
        $adequacyScore = $payload['adequacy_score'] ?? null;
        $aiComment = $payload['ai_comment'] ?? null;

        if (! is_int($questionId) && ! is_string($questionId)) {
            throw new InvalidArgumentException('Interview question id must be int|string.');
        }

        if (! is_numeric($answerScore)) {
            throw new InvalidArgumentException('Answer score must be numeric.');
        }

        if (! is_numeric($adequacyScore)) {
            throw new InvalidArgumentException('Adequacy score must be numeric.');
        }

        if (! is_string($aiComment) || trim($aiComment) === '') {
            throw new InvalidArgumentException('AI comment is required.');
        }

        $followUpResults = [];

        if ($parseFollowUps && is_array($payload['follow_ups'] ?? null)) {
            foreach ($payload['follow_ups'] as $followUp) {
                if (is_array($followUp)) {
                    $followUpResults[] = self::fromArray($followUp, parseFollowUps: false);
                }
            }
        }

        return new self(
            interviewQuestionId: (int) $questionId,
            answerScore: max(1, min(10, round((float) $answerScore, 2))),
            adequacyScore: max(1, min(10, round((float) $adequacyScore, 2))),
            aiComment: trim($aiComment),
            followUpResults: $followUpResults,
        );
    }
}
