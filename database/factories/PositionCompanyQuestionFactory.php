<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PositionCompanyQuestion>
 */
class PositionCompanyQuestionFactory extends Factory
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
            'question' => fake()->sentence(5),
            'answer' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
