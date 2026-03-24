<?php

namespace Database\Factories;

use App\Models\AiPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPrompt>
 */
class AiPromptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature' => fake()->unique()->slug(2),
            'type' => 'system_prompt',
            'content' => fake()->paragraphs(3, true),
            'description' => fake()->sentence(),
            'available_placeholders' => [],
        ];
    }
}
