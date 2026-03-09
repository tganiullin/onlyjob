<?php

namespace Tests\Feature;

use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use App\Models\Position;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_has_many_questions(): void
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

        $this->assertCount(2, $position->fresh()->questions);
    }

    public function test_position_is_archived_instead_of_hard_deleted(): void
    {
        $position = Position::factory()->create();

        $question = Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $position->delete();

        $this->assertSoftDeleted('positions', [
            'id' => $position->id,
        ]);

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'position_id' => $position->id,
        ]);
    }

    public function test_position_attributes_are_cast_to_enums(): void
    {
        $position = Position::factory()->create([
            'level' => PositionLevel::Senior->value,
            'answer_time_seconds' => PositionAnswerTime::TwoMinutesThirtySeconds->value,
        ]);

        $position = $position->fresh();

        $this->assertSame(PositionLevel::Senior, $position->level);
        $this->assertSame(PositionAnswerTime::TwoMinutesThirtySeconds, $position->answer_time_seconds);
    }

    public function test_question_sort_order_is_assigned_automatically(): void
    {
        $position = Position::factory()->create();

        $firstQuestion = $position->questions()->create([
            'text' => 'First question?',
            'evaluation_instructions' => null,
        ]);

        $secondQuestion = $position->questions()->create([
            'text' => 'Second question?',
            'evaluation_instructions' => null,
        ]);

        $this->assertSame(1, $firstQuestion->sort_order);
        $this->assertSame(2, $secondQuestion->sort_order);
    }

    public function test_question_sort_order_can_be_swapped_without_unique_constraint_error(): void
    {
        $position = Position::factory()->create();

        $firstQuestion = $position->questions()->create([
            'text' => 'First question?',
            'sort_order' => 1,
        ]);

        $secondQuestion = $position->questions()->create([
            'text' => 'Second question?',
            'sort_order' => 2,
        ]);

        DB::table('questions')
            ->whereIn('id', [$firstQuestion->id, $secondQuestion->id])
            ->update([
                'sort_order' => DB::raw(
                    sprintf(
                        'CASE WHEN `id` = %d THEN 2 WHEN `id` = %d THEN 1 END',
                        $firstQuestion->id,
                        $secondQuestion->id,
                    ),
                ),
                'updated_at' => now(),
            ]);

        $this->assertDatabaseHas('questions', [
            'id' => $firstQuestion->id,
            'sort_order' => 2,
        ]);

        $this->assertDatabaseHas('questions', [
            'id' => $secondQuestion->id,
            'sort_order' => 1,
        ]);
    }
}
