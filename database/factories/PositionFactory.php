<?php

namespace Database\Factories;

use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'minimum_score' => fake()->numberBetween(1, 10),
            'answer_time_seconds' => fake()->randomElement(array_column(PositionAnswerTime::cases(), 'value')),
            'level' => fake()->randomElement(array_column(PositionLevel::cases(), 'value')),
            'is_public' => false,
            'public_token' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (): array => [
            'is_public' => true,
            'public_token' => Str::random(40),
        ]);
    }
}
