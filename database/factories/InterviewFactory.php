<?php

namespace Database\Factories;

use App\Enums\InterviewStatus;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Interview>
 */
class InterviewFactory extends Factory
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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'status' => InterviewStatus::Pending->value,
            'score' => null,
            'candidate_feedback_rating' => fake()->optional()->numberBetween(1, 5),
            'summary' => fake()->optional()->sentence(),
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
