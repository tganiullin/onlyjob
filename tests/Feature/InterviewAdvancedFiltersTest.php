<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Filament\Resources\Interviews\Pages\ListInterviews;
use App\Models\Interview;
use App\Models\Position;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InterviewAdvancedFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_list_page_renders_with_advanced_filters(): void
    {
        Interview::factory()->count(3)->create();

        Livewire::test(ListInterviews::class)
            ->assertSuccessful()
            ->assertTableFilterVisible('status')
            ->assertTableFilterVisible('position_id')
            ->assertTableFilterVisible('completed_at');
    }

    public function test_interviews_are_sorted_by_created_at_desc_by_default(): void
    {
        $olderInterview = Interview::factory()->create([
            'created_at' => now()->subDay(),
        ]);
        $newerInterview = Interview::factory()->create([
            'created_at' => now(),
        ]);

        Livewire::test(ListInterviews::class)
            ->assertCanSeeTableRecords([$newerInterview, $olderInterview], inOrder: true);
    }

    public function test_quick_status_filter_still_works(): void
    {
        $pending = Interview::factory()->create([
            'status' => InterviewStatus::PendingInterview->value,
        ]);

        $completed = Interview::factory()->completed()->create();

        Livewire::test(ListInterviews::class)
            ->filterTable('status', InterviewStatus::PendingInterview->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$completed]);
    }

    public function test_quick_position_filter_still_works(): void
    {
        $backendPosition = Position::factory()->create(['title' => 'Backend']);
        $frontendPosition = Position::factory()->create(['title' => 'Frontend']);

        $backendInterview = Interview::factory()->create([
            'position_id' => $backendPosition->id,
        ]);

        $frontendInterview = Interview::factory()->create([
            'position_id' => $frontendPosition->id,
        ]);

        Livewire::test(ListInterviews::class)
            ->filterTable('position_id', $backendPosition->id)
            ->assertCanSeeTableRecords([$backendInterview])
            ->assertCanNotSeeTableRecords([$frontendInterview]);
    }

    public function test_quick_completed_filter_shows_only_completed(): void
    {
        $completed = Interview::factory()->completed()->create();

        $notCompleted = Interview::factory()->create([
            'status' => InterviewStatus::PendingInterview->value,
            'completed_at' => null,
        ]);

        Livewire::test(ListInterviews::class)
            ->filterTable('completed_at', true)
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$notCompleted]);
    }

    public function test_quick_completed_filter_shows_only_not_completed(): void
    {
        $completed = Interview::factory()->completed()->create();

        $notCompleted = Interview::factory()->create([
            'status' => InterviewStatus::PendingInterview->value,
            'completed_at' => null,
        ]);

        Livewire::test(ListInterviews::class)
            ->filterTable('completed_at', false)
            ->assertCanSeeTableRecords([$notCompleted])
            ->assertCanNotSeeTableRecords([$completed]);
    }
}
