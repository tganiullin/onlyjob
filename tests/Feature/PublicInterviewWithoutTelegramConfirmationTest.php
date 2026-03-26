<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Models\Position;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInterviewWithoutTelegramConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telegram.confirmation_required', false);
    }

    public function test_start_creates_interview_immediately_without_telegram_confirmation(): void
    {
        $position = $this->createPublicPositionWithQuestions();

        $response = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'telegram' => 'ivan_petrov',
            'client_request_id' => '95d22f4d-d852-4f30-8266-9a7f3dbdc18a',
            'consent' => '1',
        ]);

        $response
            ->assertOk()
            ->assertJson(['status' => 'confirmed'])
            ->assertJsonStructure(['status', 'redirect']);

        $this->assertDatabaseHas('interviews', [
            'position_id' => $position->id,
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'telegram' => 'ivan_petrov',
            'status' => InterviewStatus::PendingInterview->value,
        ]);

        $this->assertDatabaseCount('interview_telegram_confirmations', 0);
        $this->assertDatabaseCount('interview_questions', 2);
    }

    public function test_start_still_requires_telegram_field(): void
    {
        $position = $this->createPublicPositionWithQuestions();

        $response = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'client_request_id' => '95d22f4d-d852-4f30-8266-9a7f3dbdc18a',
            'consent' => '1',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telegram']);
    }

    public function test_interview_run_accessible_without_telegram_confirmed_at(): void
    {
        $position = $this->createPublicPositionWithQuestions();

        $startResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'telegram' => 'ivan_petrov',
            'client_request_id' => '95d22f4d-d852-4f30-8266-9a7f3dbdc18a',
            'consent' => '1',
        ]);

        $redirect = $startResponse->json('redirect');

        $runResponse = $this->get($redirect);
        $runResponse->assertOk();
    }

    public function test_full_flow_answer_all_questions_without_telegram_confirmation(): void
    {
        $position = $this->createPublicPositionWithQuestions();

        $startResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'telegram' => 'ivan_petrov',
            'client_request_id' => '95d22f4d-d852-4f30-8266-9a7f3dbdc18a',
            'consent' => '1',
        ]);

        $redirect = $startResponse->json('redirect');
        $this->get($redirect)->assertOk();

        $interviewId = session('public_interview_id');
        $interview = \App\Models\Interview::query()->findOrFail($interviewId);
        $questions = $interview->interviewQuestions()->orderBy('sort_order')->get();

        $this->assertCount(2, $questions);

        $this->postJson(route('public-interviews.questions.answer', [
            'interview' => $interview,
            'interviewQuestion' => $questions[0],
        ]), ['candidate_answer' => 'Answer 1'])->assertOk();

        $lastResponse = $this->postJson(route('public-interviews.questions.answer', [
            'interview' => $interview,
            'interviewQuestion' => $questions[1],
        ]), ['candidate_answer' => 'Answer 2'])->assertOk();

        $lastResponse->assertJson(['completed' => true]);

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'status' => InterviewStatus::Completed->value,
        ]);
    }

    private function createPublicPositionWithQuestions(): Position
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'First question',
            'sort_order' => 1,
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'Second question',
            'sort_order' => 2,
        ]);

        return $position;
    }
}
