<?php

namespace Tests\Feature;

use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use App\Filament\Resources\Positions\Pages\CreatePosition;
use App\Models\Position;
use App\Models\PositionCompanyQuestion;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeAiProvider;
use Tests\TestCase;

class CreatePositionAiQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_saves_ai_generated_questions_and_keeps_title_unchanged(): void
    {
        $undoRepeaterFake = Repeater::fake();

        try {
            $user = User::factory()->create();
            $this->actingAs($user);

            Filament::setCurrentPanel(Filament::getPanel('admin'));

            $provider = new FakeAiProvider([
                [
                    'questions' => [
                        [
                            'text' => 'How would you design a fault-tolerant queue pipeline?',
                            'evaluation_instructions' => 'Check depth of architecture reasoning and operational trade-offs.',
                        ],
                        [
                            'text' => 'How do you handle schema changes without downtime?',
                            'evaluation_instructions' => 'Check migration strategy, rollback planning, and production safety.',
                        ],
                    ],
                ],
            ]);
            $this->useFakeAiProvider($provider);

            $generatedQuestions = app(QuestionGenerator::class)->generate([
                'description' => 'Senior backend engineer for high-load APIs and asynchronous processing.',
                'level' => PositionLevel::Senior->value,
                'questions_count' => 2,
                'focus' => 'hard_skills',
            ]);

            $this->assertSame(1, $provider->callCount);

            $component = Livewire::test(CreatePosition::class)
                ->fillForm([
                    'title' => 'Backend Engineer',
                    'minimum_score' => 7,
                    'answer_time_seconds' => PositionAnswerTime::TwoMinutesThirtySeconds->value,
                    'level' => PositionLevel::Senior->value,
                    'questions' => $generatedQuestions,
                    'companyQuestions' => [
                        [
                            'question' => 'Как часто индексация зарплаты?',
                            'answer' => 'Обычно раз в год по результатам performance review.',
                        ],
                        [
                            'question' => 'Есть ли удаленный формат?',
                            'answer' => 'Да, доступен гибридный формат.',
                        ],
                    ],
                ]);

            $component
                ->assertSchemaStateSet(function (array $state): array {
                    $this->assertSame('Backend Engineer', $state['title']);
                    $this->assertCount(2, $state['questions']);
                    $this->assertCount(2, $state['companyQuestions']);

                    return [];
                })
                ->call('create')
                ->assertHasNoFormErrors()
                ->assertRedirect();

            $position = Position::query()->first();

            $this->assertNotNull($position);
            $this->assertSame('Backend Engineer', $position->title);
            $this->assertStringContainsString('"level": "senior"', $provider->requests[0]->userPrompt);
            $this->assertDatabaseHas('questions', [
                'position_id' => $position->id,
                'text' => 'How would you design a fault-tolerant queue pipeline?',
            ]);
            $this->assertDatabaseHas('questions', [
                'position_id' => $position->id,
                'text' => 'How do you handle schema changes without downtime?',
            ]);
            $this->assertDatabaseHas('position_company_questions', [
                'position_id' => $position->id,
                'question' => 'Как часто индексация зарплаты?',
            ]);
            $this->assertDatabaseHas('position_company_questions', [
                'position_id' => $position->id,
                'question' => 'Есть ли удаленный формат?',
            ]);
            $this->assertSame(2, PositionCompanyQuestion::query()->where('position_id', $position->id)->count());
        } finally {
            $undoRepeaterFake();
        }
    }

    private function useFakeAiProvider(FakeAiProvider $provider): void
    {
        config()->set('ai.default_provider', 'fake');
        config()->set('ai.providers.fake', FakeAiProvider::class);
        config()->set('ai.features.question_generation.provider', 'fake');

        app()->instance(FakeAiProvider::class, $provider);
    }
}
