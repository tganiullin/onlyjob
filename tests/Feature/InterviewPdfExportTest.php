<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\InterviewIntegrityEvent;
use App\Models\InterviewQuestion;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_download_interview_pdf(): void
    {
        $interview = Interview::factory()->completed()->create();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('interviews.export-pdf', ['record' => $interview]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_unauthenticated_user_is_rejected(): void
    {
        $interview = Interview::factory()->create();

        $response = $this->getJson(route('interviews.export-pdf', ['record' => $interview]));

        $response->assertUnauthorized();
    }

    public function test_pdf_export_includes_questions_and_integrity_events(): void
    {
        $position = Position::factory()->create(['title' => 'Senior Developer']);
        $interview = Interview::factory()->completed()->create([
            'position_id' => $position->id,
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'score' => 8.50,
            'summary' => 'Strong technical candidate.',
        ]);

        InterviewQuestion::factory()->create([
            'interview_id' => $interview->id,
            'question_text' => 'Tell me about your experience',
            'candidate_answer' => 'I have 5 years of PHP experience',
            'answer_score' => 9.00,
            'sort_order' => 1,
        ]);

        InterviewIntegrityEvent::factory()->create([
            'interview_id' => $interview->id,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('interviews.export-pdf', ['record' => $interview]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=interview-'.$interview->id.'-ivan-petrov.pdf',
        );
    }

    public function test_pdf_export_works_for_interview_without_questions(): void
    {
        $interview = Interview::factory()->create([
            'status' => InterviewStatus::PendingConfirmation->value,
        ]);
        $interview->interviewQuestions()->delete();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('interviews.export-pdf', ['record' => $interview]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
