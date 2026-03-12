<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InterviewTelegramConfirmation>
 */
class InterviewTelegramConfirmationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statusToken = Str::random(64);

        return [
            'position_id' => Position::factory(),
            'interview_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->optional()->safeEmail(),
            'expected_username' => strtolower(fake()->unique()->userName()),
            'session_fingerprint' => hash('sha256', Str::uuid()->toString()),
            'client_request_id' => (string) Str::uuid(),
            'status_token' => $statusToken,
            'token_hash' => hash('sha256', $statusToken),
            'expires_at' => now()->addMinutes(15),
            'confirmed_at' => null,
            'used_at' => null,
            'superseded_at' => null,
            'telegram_user_id' => null,
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_update_id' => null,
            'failure_reason' => null,
        ];
    }
}
