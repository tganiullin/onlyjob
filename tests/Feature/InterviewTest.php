<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\Position;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $interview = Interview::factory()->create([
            'status' => InterviewStatus::Pending->value,
        ]);

        $this->assertSame(InterviewStatus::Pending, $interview->fresh()->status);
    }
}
