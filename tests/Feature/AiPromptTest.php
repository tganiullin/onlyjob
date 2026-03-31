<?php

namespace Tests\Feature;

use App\AI\Features\CompanyQuestionsGeneration\Contracts\CompanyQuestionsGenerator;
use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Fakes\FakeAiProvider;
use Tests\TestCase;

class AiPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_content_with_placeholders_replaced(): void
    {
        AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Hello {{name}}, you are {{role}}.',
        ]);

        $result = AiPrompt::resolve('test_feature', 'system_prompt', [
            'name' => 'Alice',
            'role' => 'admin',
        ]);

        $this->assertSame('Hello Alice, you are admin.', $result);
    }

    public function test_resolve_returns_null_when_prompt_not_found(): void
    {
        $result = AiPrompt::resolve('nonexistent', 'system_prompt');

        $this->assertNull($result);
    }

    public function test_create_version_saves_current_content_as_version(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Original content',
        ]);

        $this->actingAs(User::factory()->create());

        $version = $prompt->createVersion('Initial version');

        $this->assertSame(1, $version->version_number);
        $this->assertSame('Original content', $version->content);
        $this->assertSame('Initial version', $version->change_note);
        $this->assertNotNull($version->user_id);
    }

    public function test_create_version_increments_version_number(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Content v1',
        ]);

        $prompt->createVersion('First');
        $prompt->createVersion('Second');
        $version3 = $prompt->createVersion('Third');

        $this->assertSame(3, $version3->version_number);
        $this->assertSame(3, $prompt->versions()->count());
    }

    public function test_revert_to_version_restores_content_and_creates_new_version(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Version 1 content',
        ]);

        $v1 = $prompt->createVersion('v1');

        $prompt->update(['content' => 'Version 2 content']);
        $prompt->createVersion('v2');

        $prompt->revertToVersion($v1);

        $prompt->refresh();
        $this->assertSame('Version 1 content', $prompt->content);
        $this->assertSame(3, $prompt->versions()->count());
        $this->assertStringContainsString('Reverted to version #1', $prompt->versions()->first()->change_note);
    }

    public function test_versions_are_ordered_by_version_number_descending(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Content',
        ]);

        $prompt->createVersion('First');
        $prompt->createVersion('Second');
        $prompt->createVersion('Third');

        $versions = $prompt->versions;

        $this->assertSame(3, $versions->first()->version_number);
        $this->assertSame(1, $versions->last()->version_number);
    }

    public function test_resolves_prompt_trait_uses_database_prompt(): void
    {
        AiPrompt::factory()->create(['feature' => 'question_generation', 'type' => 'system_prompt', 'content' => 'Custom prompt for {{level_label}} level.']);
        AiPrompt::factory()->create(['feature' => 'question_generation', 'type' => 'user_prompt', 'content' => 'Generate {{questions_count}} questions: {{payload_json}}']);
        AiPrompt::factory()->create(['feature' => 'question_generation', 'type' => 'level_guideline_senior', 'content' => 'Senior guideline']);
        AiPrompt::factory()->create(['feature' => 'question_generation', 'type' => 'focus_guideline_hard_skills', 'content' => 'Hard skills guideline']);
        AiPrompt::factory()->create(['feature' => 'question_generation', 'type' => 'answer_time_guideline', 'content' => 'Time guideline']);
        AiPrompt::factory()->create(['feature' => 'question_generation', 'type' => 'output_language_template', 'content' => 'Write in {{language}}.']);

        $provider = new FakeAiProvider([
            [
                'questions' => [
                    [
                        'text' => 'Test question?',
                        'evaluation_instructions' => 'Check understanding.',
                    ],
                ],
            ],
        ]);
        $this->useFakeAiProvider($provider, 'question_generation');

        app(QuestionGenerator::class)->generate([
            'description' => 'PHP developer',
            'level' => PositionLevel::Senior->value,
            'questions_count' => 1,
            'focus' => 'hard_skills',
            'answer_time_seconds' => PositionAnswerTime::TwoMinutesThirtySeconds->value,
        ]);

        $this->assertSame('Custom prompt for Senior level.', $provider->requests[0]->systemPrompt);
    }

    public function test_resolves_prompt_trait_throws_when_no_database_prompt(): void
    {
        $provider = new FakeAiProvider([]);
        $this->useFakeAiProvider($provider, 'company_questions_generation');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI prompt not found: feature=company_questions_generation');

        app(CompanyQuestionsGenerator::class)->generate([
            'description' => 'Tech company description.',
        ]);
    }

    public function test_version_belongs_to_prompt(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Content',
        ]);

        $version = $prompt->createVersion();

        $this->assertTrue($version->prompt->is($prompt));
    }

    public function test_version_belongs_to_user_when_authenticated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $prompt = AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Content',
        ]);

        $version = $prompt->createVersion();

        $this->assertTrue($version->user->is($user));
    }

    public function test_cascade_deletes_versions_when_prompt_deleted(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'test_feature',
            'type' => 'system_prompt',
            'content' => 'Content',
        ]);

        $prompt->createVersion('v1');
        $prompt->createVersion('v2');

        $promptId = $prompt->id;
        $prompt->delete();

        $this->assertSame(0, AiPromptVersion::query()->where('ai_prompt_id', $promptId)->count());
    }

    private function useFakeAiProvider(FakeAiProvider $provider, string $feature): void
    {
        config()->set('ai.default_provider', 'fake');
        config()->set('ai.providers.fake', FakeAiProvider::class);
        config()->set("ai.features.{$feature}.provider", 'fake');

        app()->instance(FakeAiProvider::class, $provider);
    }
}
