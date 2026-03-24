<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Filament\Widgets\InterviewsByPositionChart;
use App\Filament\Widgets\InterviewsChart;
use App\Filament\Widgets\InterviewStatsOverview;
use App\Filament\Widgets\InterviewStatusChart;
use App\Models\Interview;
use App\Models\Position;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InterviewDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_stats_overview_renders_with_no_data(): void
    {
        Livewire::test(InterviewStatsOverview::class)
            ->assertSuccessful();
    }

    public function test_stats_overview_shows_correct_counts(): void
    {
        $position = Position::factory()->create();

        Interview::factory()->for($position)->create([
            'status' => InterviewStatus::ReviewedPassed,
            'score' => 8.5,
        ]);

        Interview::factory()->for($position)->create([
            'status' => InterviewStatus::ReviewedFailed,
            'score' => 3.0,
        ]);

        Interview::factory()->for($position)->create([
            'status' => InterviewStatus::InProgress,
        ]);

        Livewire::test(InterviewStatsOverview::class)
            ->assertSuccessful();
    }

    public function test_interviews_chart_renders(): void
    {
        Livewire::test(InterviewsChart::class)
            ->assertSuccessful();
    }

    public function test_interviews_chart_renders_with_data(): void
    {
        $position = Position::factory()->create();
        Interview::factory()->count(3)->for($position)->create();

        Livewire::test(InterviewsChart::class)
            ->assertSuccessful();
    }

    public function test_interview_status_chart_renders(): void
    {
        Livewire::test(InterviewStatusChart::class)
            ->assertSuccessful();
    }

    public function test_interview_status_chart_renders_with_data(): void
    {
        $position = Position::factory()->create();

        Interview::factory()->for($position)->create([
            'status' => InterviewStatus::ReviewedPassed,
            'score' => 7.0,
        ]);

        Interview::factory()->for($position)->create([
            'status' => InterviewStatus::InProgress,
        ]);

        Livewire::test(InterviewStatusChart::class)
            ->assertSuccessful();
    }

    public function test_interviews_by_position_chart_renders(): void
    {
        Livewire::test(InterviewsByPositionChart::class)
            ->assertSuccessful();
    }

    public function test_interviews_by_position_chart_renders_with_data(): void
    {
        $positionA = Position::factory()->create(['title' => 'Backend Developer']);
        $positionB = Position::factory()->create(['title' => 'Frontend Developer']);

        Interview::factory()->count(3)->for($positionA)->create();
        Interview::factory()->count(1)->for($positionB)->create();

        Livewire::test(InterviewsByPositionChart::class)
            ->assertSuccessful();
    }
}
