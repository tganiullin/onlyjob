<?php

namespace Database\Factories;

use App\Enums\QuestionAnswerMode;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'text' => fake()->sentence().'?',
            'sort_order' => fake()->numberBetween(1, 10),
            'evaluation_instructions' => fake()->optional()->sentence(),
            'answer_mode' => QuestionAnswerMode::Voice,
        ];
    }

    public function textMode(): static
    {
        return $this->state(fn (): array => [
            'answer_mode' => QuestionAnswerMode::Text,
        ]);
    }
}
