<?php

namespace Tests\Unit;

use App\AI\Features\FollowUpEvaluation\Data\FollowUpEvaluationResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FollowUpEvaluationResultTest extends TestCase
{
    public function test_from_array_parses_follow_up_needed(): void
    {
        $result = FollowUpEvaluationResult::fromArray([
            'needs_follow_up' => true,
            'score_estimate' => 3.5,
            'follow_up_question' => 'Расскажите подробнее о SOLID.',
        ]);

        $this->assertTrue($result->needsFollowUp);
        $this->assertSame(3.5, $result->scoreEstimate);
        $this->assertSame('Расскажите подробнее о SOLID.', $result->followUpQuestion);
    }

    public function test_from_array_parses_no_follow_up(): void
    {
        $result = FollowUpEvaluationResult::fromArray([
            'needs_follow_up' => false,
            'score_estimate' => 7.0,
            'follow_up_question' => null,
        ]);

        $this->assertFalse($result->needsFollowUp);
        $this->assertSame(7.0, $result->scoreEstimate);
        $this->assertNull($result->followUpQuestion);
    }

    public function test_from_array_clamps_score_to_valid_range(): void
    {
        $low = FollowUpEvaluationResult::fromArray([
            'needs_follow_up' => false,
            'score_estimate' => -5,
            'follow_up_question' => null,
        ]);

        $high = FollowUpEvaluationResult::fromArray([
            'needs_follow_up' => false,
            'score_estimate' => 15,
            'follow_up_question' => null,
        ]);

        $this->assertSame(1.0, $low->scoreEstimate);
        $this->assertSame(10.0, $high->scoreEstimate);
    }

    public function test_from_array_falls_back_to_no_follow_up_when_question_is_empty(): void
    {
        $result = FollowUpEvaluationResult::fromArray([
            'needs_follow_up' => true,
            'score_estimate' => 3.0,
            'follow_up_question' => '',
        ]);

        $this->assertFalse($result->needsFollowUp);
        $this->assertNull($result->followUpQuestion);
    }

    public function test_from_array_throws_on_missing_score(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FollowUpEvaluationResult::fromArray([
            'needs_follow_up' => false,
            'follow_up_question' => null,
        ]);
    }

    public function test_no_follow_up_factory_method(): void
    {
        $result = FollowUpEvaluationResult::noFollowUp(8.0);

        $this->assertFalse($result->needsFollowUp);
        $this->assertSame(8.0, $result->scoreEstimate);
        $this->assertNull($result->followUpQuestion);
    }
}
