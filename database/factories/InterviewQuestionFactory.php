<?php

namespace Database\Factories;

use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InterviewQuestion>
 */
class InterviewQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_id' => Interview::factory(),
            'question_id' => null,
            'parent_question_id' => null,
            'question_text' => fake()->sentence().'?',
            'evaluation_instructions_snapshot' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(1, 10),
            'candidate_answer' => fake()->optional()->paragraph(),
            'ai_comment' => fake()->optional()->sentence(),
            'answer_score' => fake()->optional()->randomFloat(2, 1, 10),
        ];
    }

    public function followUp(int $parentQuestionId): static
    {
        return $this->state(fn (): array => [
            'parent_question_id' => $parentQuestionId,
            'question_id' => null,
            'candidate_answer' => null,
            'answer_score' => null,
            'adequacy_score' => null,
            'ai_comment' => null,
        ]);
    }
}
