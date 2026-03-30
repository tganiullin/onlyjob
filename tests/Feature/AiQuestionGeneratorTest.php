<?php

namespace Tests\Feature;

use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use Database\Seeders\AiPromptSeeder;
use InvalidArgumentException;
use Tests\Fakes\FakeAiProvider;
use Tests\TestCase;

class AiQuestionGeneratorTest extends TestCase
{
    public function test_it_generates_questions_and_uses_position_level_in_prompt(): void
    {
        $this->seed(AiPromptSeeder::class);
        $provider = new FakeAiProvider([
            [
                'questions' => [
                    [
                        'text' => 'How would you design idempotent queue workers in Laravel?',
                        'evaluation_instructions' => 'Check architecture depth, retry strategy, and trade-off clarity.',
                    ],
                    [
                        'text' => 'How do you approach database performance bottlenecks in production?',
                        'evaluation_instructions' => 'Check practical diagnostics, indexing strategy, and rollback awareness.',
                    ],
                ],
            ],
        ]);
        $this->useFakeAiProvider($provider);

        $questions = app(QuestionGenerator::class)->generate([
            'description' => 'Senior backend role focused on scalable APIs and distributed background processing.',
            'level' => PositionLevel::Senior->value,
            'questions_count' => 2,
            'focus' => 'hard_skills',
            'answer_time_seconds' => PositionAnswerTime::OneMinuteThirtySeconds->value,
        ]);

        $this->assertCount(2, $questions);
        $this->assertSame('How would you design idempotent queue workers in Laravel?', $questions[0]['text']);
        $this->assertSame(1, $provider->callCount);
        $this->assertStringContainsString('Senior', $provider->requests[0]->systemPrompt);
        $this->assertStringContainsString('Russian', $provider->requests[0]->systemPrompt);
        $this->assertStringContainsString('(90 seconds)', $provider->requests[0]->systemPrompt);
        $this->assertStringContainsString('Prefer scenario and problem-solving questions', $provider->requests[0]->systemPrompt);
        $this->assertStringContainsString('Do not rely on "tell me about your past experience" phrasing', $provider->requests[0]->systemPrompt);
        $this->assertStringContainsString('"level": "senior"', $provider->requests[0]->userPrompt);
        $this->assertStringContainsString('"answer_time_seconds": 90', $provider->requests[0]->userPrompt);
        $this->assertStringContainsString('"answer_time_label":', $provider->requests[0]->userPrompt);
        $this->assertSame(2, data_get($provider->requests[0]->jsonSchema, 'properties.questions.minItems'));
        $this->assertSame(2, data_get($provider->requests[0]->jsonSchema, 'properties.questions.maxItems'));
    }

    public function test_it_throws_exception_when_generated_payload_is_invalid(): void
    {
        $this->seed(AiPromptSeeder::class);

        $provider = new FakeAiProvider([
            [
                'foo' => 'bar',
            ],
        ]);
        $this->useFakeAiProvider($provider);

        $this->expectException(InvalidArgumentException::class);

        app(QuestionGenerator::class)->generate([
            'description' => 'Middle full-stack role with focus on REST APIs and teamwork.',
            'level' => PositionLevel::Middle->value,
            'questions_count' => 1,
            'focus' => 'mixed',
        ]);
    }

    private function useFakeAiProvider(FakeAiProvider $provider): void
    {
        config()->set('ai.default_provider', 'fake');
        config()->set('ai.providers.fake', FakeAiProvider::class);
        config()->set('ai.features.question_generation.provider', 'fake');

        app()->instance(FakeAiProvider::class, $provider);
    }
}
