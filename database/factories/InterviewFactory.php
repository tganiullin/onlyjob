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
            'telegram' => '@'.fake()->unique()->userName(),
            'telegram_confirmed_at' => null,
            'telegram_user_id' => null,
            'telegram_chat_id' => null,
            'telegram_confirmed_username' => null,
            'phone' => fake()->optional()->phoneNumber(),
            'status' => InterviewStatus::PendingConfirmation->value,
            'score' => null,
            'candidate_feedback_rating' => fake()->optional()->numberBetween(1, 5),
            'summary' => fake()->optional()->sentence(),
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => InterviewStatus::Completed->value,
            'completed_at' => now(),
        ]);
    }
}
