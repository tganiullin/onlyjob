<?php

namespace Tests\Feature;

use App\Filament\Resources\AiPrompts\AiPromptResource;
use App\Filament\Resources\AiPrompts\Pages\EditAiPrompt;
use App\Filament\Resources\AiPrompts\Pages\ListAiPrompts;
use App\Filament\Resources\AiPrompts\Pages\ViewAiPrompt;
use App\Models\AiPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiPromptFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_list_page_renders_and_shows_prompts(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'question_generation',
            'type' => 'system_prompt',
            'description' => 'Question Generation — System Prompt',
        ]);

        Livewire::test(ListAiPrompts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$prompt]);
    }

    public function test_view_page_renders_for_prompt(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'question_generation',
            'type' => 'system_prompt',
        ]);

        Livewire::test(ViewAiPrompt::class, ['record' => $prompt->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_edit_page_renders_for_prompt(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'question_generation',
            'type' => 'system_prompt',
        ]);

        Livewire::test(EditAiPrompt::class, ['record' => $prompt->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_editing_prompt_creates_version_with_old_content(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'question_generation',
            'type' => 'system_prompt',
            'content' => 'Original prompt content',
        ]);

        Livewire::test(EditAiPrompt::class, ['record' => $prompt->getRouteKey()])
            ->fillForm([
                'content' => 'Updated prompt content',
                'change_note' => 'Improved instructions',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $prompt->refresh();
        $this->assertSame('Updated prompt content', $prompt->content);

        $version = $prompt->versions()->first();
        $this->assertNotNull($version);
        $this->assertSame('Original prompt content', $version->content);
        $this->assertSame('Improved instructions', $version->change_note);
        $this->assertSame(1, $version->version_number);
        $this->assertSame($this->user->id, $version->user_id);
    }

    public function test_editing_prompt_without_change_note_still_creates_version(): void
    {
        $prompt = AiPrompt::factory()->create([
            'feature' => 'interview_review',
            'type' => 'system_prompt',
            'content' => 'Old content',
        ]);

        Livewire::test(EditAiPrompt::class, ['record' => $prompt->getRouteKey()])
            ->fillForm([
                'content' => 'New content',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $prompt->refresh();
        $this->assertSame('New content', $prompt->content);
        $this->assertSame(1, $prompt->versions()->count());
        $this->assertNull($prompt->versions()->first()->change_note);
    }

    public function test_create_page_is_not_accessible(): void
    {
        $this->assertFalse(AiPromptResource::canCreate());
    }
}
