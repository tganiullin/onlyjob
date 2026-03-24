<?php

namespace Database\Factories;

use App\Enums\InterviewIntegrityEventType;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InterviewIntegrityEvent>
 */
class InterviewIntegrityEventFactory extends Factory
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
            'interview_question_id' => null,
            'event_type' => fake()->randomElement(InterviewIntegrityEventType::values()),
            'occurred_at' => now()->subSeconds(random_int(1, 600)),
            'payload' => [
                'source' => 'factory',
            ],
        ];
    }

    public function forInterviewQuestion(?InterviewQuestion $interviewQuestion = null): static
    {
        return $this->state(function (array $attributes) use ($interviewQuestion): array {
            $resolvedInterviewQuestion = $interviewQuestion ?? InterviewQuestion::factory()->create();

            return [
                'interview_id' => $resolvedInterviewQuestion->interview_id,
                'interview_question_id' => $resolvedInterviewQuestion->id,
            ];
        });
    }
}
