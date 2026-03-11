<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Filament\Resources\Interviews\Pages\ListInterviews;
use App\Models\Interview;
use App\Models\Position;
use App\Models\Question;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InterviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_interview_creates_snapshot_questions(): void
    {
        $position = Position::factory()->create();

        $firstQuestion = Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'What is Eloquent ORM?',
            'evaluation_instructions' => 'Look for practical examples',
            'sort_order' => 1,
        ]);

        $secondQuestion = Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'How does dependency injection work?',
            'evaluation_instructions' => 'Mention service container',
            'sort_order' => 2,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $this->assertCount(2, $interview->fresh()->interviewQuestions);

        $this->assertDatabaseHas('interview_questions', [
            'interview_id' => $interview->id,
            'question_id' => $firstQuestion->id,
            'question_text' => 'What is Eloquent ORM?',
            'evaluation_instructions_snapshot' => 'Look for practical examples',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('interview_questions', [
            'interview_id' => $interview->id,
            'question_id' => $secondQuestion->id,
            'question_text' => 'How does dependency injection work?',
            'evaluation_instructions_snapshot' => 'Mention service container',
            'sort_order' => 2,
        ]);
    }

    public function test_interview_snapshot_is_independent_from_position_question_updates(): void
    {
        $position = Position::factory()->create();
        $question = Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'Original question?',
            'evaluation_instructions' => 'Original instructions',
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $question->update([
            'text' => 'Updated question?',
            'evaluation_instructions' => 'Updated instructions',
        ]);

        $this->assertDatabaseHas('interview_questions', [
            'interview_id' => $interview->id,
            'question_id' => $question->id,
            'question_text' => 'Original question?',
            'evaluation_instructions_snapshot' => 'Original instructions',
        ]);
    }

    public function test_interview_deletion_cascades_interview_questions(): void
    {
        $position = Position::factory()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $snapshotQuestionIds = $interview->interviewQuestions()->pluck('id')->all();
        $interview->delete();

        foreach ($snapshotQuestionIds as $snapshotQuestionId) {
            $this->assertDatabaseMissing('interview_questions', [
                'id' => $snapshotQuestionId,
            ]);
        }
    }

    public function test_interview_score_is_recalculated_when_answer_scores_change(): void
    {
        $position = Position::factory()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $snapshotQuestions = $interview->interviewQuestions()->orderBy('sort_order')->get();
        $firstSnapshotQuestion = $snapshotQuestions[0];
        $secondSnapshotQuestion = $snapshotQuestions[1];

        $firstSnapshotQuestion->update(['answer_score' => 6.00]);
        $secondSnapshotQuestion->update(['answer_score' => 8.00]);

        $this->assertSame('7.00', $interview->fresh()->score);

        $secondSnapshotQuestion->update(['answer_score' => 10.00]);
        $this->assertSame('8.00', $interview->fresh()->score);

        $firstSnapshotQuestion->update(['answer_score' => null]);
        $this->assertSame('10.00', $interview->fresh()->score);

        $secondSnapshotQuestion->delete();
        $this->assertNull($interview->fresh()->score);
    }

    public function test_interview_status_is_cast_to_enum(): void
    {
        $pendingInterview = Interview::factory()->create([
            'status' => InterviewStatus::Pending->value,
        ]);

        $completedInterview = Interview::factory()->completed()->create();

        $this->assertSame(InterviewStatus::Pending, $pendingInterview->fresh()->status);
        $this->assertSame(InterviewStatus::Completed, $completedInterview->fresh()->status);
    }

    public function test_admin_list_can_filter_interviews_by_status_and_position(): void
    {
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $backendPosition = Position::factory()->create([
            'title' => 'Backend Engineer',
        ]);

        $frontendPosition = Position::factory()->create([
            'title' => 'Frontend Engineer',
        ]);

        $pendingBackendInterview = Interview::factory()->create([
            'position_id' => $backendPosition->id,
            'status' => InterviewStatus::Pending->value,
        ]);

        $completedBackendInterview = Interview::factory()->create([
            'position_id' => $backendPosition->id,
            'status' => InterviewStatus::Completed->value,
            'completed_at' => now(),
        ]);

        $passedFrontendInterview = Interview::factory()->create([
            'position_id' => $frontendPosition->id,
            'status' => InterviewStatus::Passed->value,
            'completed_at' => now(),
        ]);

        Livewire::test(ListInterviews::class)
            ->assertTableFilterVisible('status')
            ->assertTableFilterVisible('position_id')
            ->assertTableFilterVisible('completed_at')
            ->filterTable('status', InterviewStatus::Pending->value)
            ->assertCanSeeTableRecords([$pendingBackendInterview])
            ->assertCanNotSeeTableRecords([$completedBackendInterview, $passedFrontendInterview])
            ->removeTableFilter('status')
            ->filterTable('position_id', $backendPosition->id)
            ->assertCanSeeTableRecords([$pendingBackendInterview, $completedBackendInterview])
            ->assertCanNotSeeTableRecords([$passedFrontendInterview]);
    }
}
