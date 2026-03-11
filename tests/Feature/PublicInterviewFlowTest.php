<?php

namespace Tests\Feature;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\Position;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicInterviewFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_position_entry_is_not_available_for_private_or_unknown_token(): void
    {
        Position::factory()->create([
            'is_public' => false,
            'public_token' => 'private-position-token',
        ]);

        $this->get(route('public-positions.show', ['token' => 'private-position-token']))
            ->assertNotFound();

        $this->get(route('public-positions.show', ['token' => 'missing-token']))
            ->assertNotFound();
    }

    public function test_start_creates_new_pending_interview_from_public_position(): void
    {
        $position = Position::factory()->public()->create([
            'public_token' => 'public-position-token',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'Tell about your recent project.',
            'sort_order' => 1,
            'evaluation_instructions' => 'Look for ownership and measurable impact.',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'How do you handle deadlines?',
            'sort_order' => 2,
            'evaluation_instructions' => 'Check prioritization and communication.',
        ]);

        $response = $this->post(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'consent' => '1',
        ]);

        $interview = Interview::query()->firstOrFail();

        $response->assertRedirect(route('public-interviews.run', ['interview' => $interview]));
        $response->assertSessionHas('public_interview_id', $interview->id);

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'position_id' => $position->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'status' => InterviewStatus::Pending->value,
        ]);

        $this->assertCount(2, $interview->fresh()->interviewQuestions);

        $this->assertDatabaseHas('interview_questions', [
            'interview_id' => $interview->id,
            'question_text' => 'Tell about your recent project.',
            'sort_order' => 1,
            'evaluation_instructions_snapshot' => 'Look for ownership and measurable impact.',
        ]);
    }

    public function test_answer_flow_saves_answers_and_marks_interview_as_completed_after_last_question(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'First question?',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'Second question?',
        ]);

        $this->post(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'consent' => '1',
        ]);

        $interview = Interview::query()
            ->with('interviewQuestions')
            ->firstOrFail();

        $firstInterviewQuestion = $interview->interviewQuestions->sortBy('sort_order')->values()[0];
        $secondInterviewQuestion = $interview->interviewQuestions->sortBy('sort_order')->values()[1];

        $firstAnswerResponse = $this->postJson(
            route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $firstInterviewQuestion,
            ]),
            [
                'candidate_answer' => 'This is my first answer.',
            ],
        );

        $firstAnswerResponse
            ->assertOk()
            ->assertJson([
                'completed' => false,
                'next_question' => [
                    'id' => $secondInterviewQuestion->id,
                ],
            ]);

        $interview->refresh();
        $this->assertSame(InterviewStatus::Pending, $interview->status);
        $this->assertNull($interview->completed_at);
        $this->assertDatabaseHas('interview_questions', [
            'id' => $firstInterviewQuestion->id,
            'candidate_answer' => 'This is my first answer.',
        ]);

        $secondAnswerResponse = $this->postJson(
            route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $secondInterviewQuestion,
            ]),
            [
                'candidate_answer' => 'This is my second answer.',
            ],
        );

        $secondAnswerResponse
            ->assertOk()
            ->assertJson([
                'completed' => true,
                'message' => 'Спасибо! Вы успешно завершили первый этап интервью.',
            ]);

        $interview->refresh();

        $this->assertSame(InterviewStatus::Completed, $interview->status);
        $this->assertNotNull($interview->completed_at);
        $this->assertDatabaseHas('interview_questions', [
            'id' => $secondInterviewQuestion->id,
            'candidate_answer' => 'This is my second answer.',
        ]);
    }

    public function test_answer_flow_rejects_out_of_order_answers(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'First question?',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'Second question?',
        ]);

        $this->post(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'consent' => '1',
        ]);

        $interview = Interview::query()
            ->with('interviewQuestions')
            ->firstOrFail();

        $firstInterviewQuestion = $interview->interviewQuestions->sortBy('sort_order')->values()[0];
        $secondInterviewQuestion = $interview->interviewQuestions->sortBy('sort_order')->values()[1];

        $response = $this->postJson(
            route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $secondInterviewQuestion,
            ]),
            [
                'candidate_answer' => 'Trying to skip first question.',
            ],
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'completed' => false,
                'next_question' => [
                    'id' => $firstInterviewQuestion->id,
                ],
            ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $secondInterviewQuestion->id,
            'candidate_answer' => null,
        ]);

        $interview->refresh();
        $this->assertSame(InterviewStatus::Pending, $interview->status);
        $this->assertNull($interview->completed_at);
    }

    public function test_answer_endpoint_is_forbidden_without_active_public_interview_session(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);
        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();

        $this->withSession(['public_interview_id' => null])
            ->postJson(route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]), [
                'candidate_answer' => 'Attempt without session.',
            ])
            ->assertForbidden();
    }

    public function test_transcribe_endpoint_returns_text_from_stt_provider(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $this->post(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Nora',
            'last_name' => 'Hall',
            'email' => 'nora.hall@example.com',
            'consent' => '1',
        ]);

        $interview = Interview::query()->firstOrFail();

        $fakeSpeechTranscriber = new class implements SpeechTranscriber
        {
            public string $language = '';

            public function transcribe(UploadedFile $audioFile, string $language): string
            {
                $this->language = $language;

                return 'Hello from OpenAI transcription.';
            }
        };

        $this->app->instance(SpeechTranscriber::class, $fakeSpeechTranscriber);

        $response = $this->post(route('public-interviews.transcribe', ['interview' => $interview]), [
            'audio' => UploadedFile::fake()->create('speech.webm', 128, 'audio/webm'),
            'language' => 'auto',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'text' => 'Hello from OpenAI transcription.',
            ]);

        $this->assertSame('auto', $fakeSpeechTranscriber->language);
    }

    public function test_transcribe_endpoint_is_forbidden_without_active_public_interview_session(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $this->withSession(['public_interview_id' => null])
            ->post(route('public-interviews.transcribe', ['interview' => $interview]), [
                'audio' => UploadedFile::fake()->create('speech.webm', 128, 'audio/webm'),
                'language' => 'en-US',
            ])
            ->assertForbidden();
    }

    public function test_answer_and_transcribe_endpoints_are_locked_for_terminal_statuses(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::Passed->value,
            'completed_at' => now(),
        ]);
        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();

        $answerResponse = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]), [
                'candidate_answer' => 'This answer should not be saved.',
            ]);

        $answerResponse
            ->assertOk()
            ->assertJson([
                'completed' => true,
                'message' => 'Спасибо! Вы успешно завершили первый этап интервью.',
            ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $interviewQuestion->id,
            'candidate_answer' => null,
        ]);

        $fakeSpeechTranscriber = new class implements SpeechTranscriber
        {
            public bool $wasCalled = false;

            public function transcribe(UploadedFile $audioFile, string $language): string
            {
                $this->wasCalled = true;

                return 'This should not be returned.';
            }
        };

        $this->app->instance(SpeechTranscriber::class, $fakeSpeechTranscriber);

        $transcribeResponse = $this->withSession(['public_interview_id' => $interview->id])
            ->post(route('public-interviews.transcribe', ['interview' => $interview]), [
                'audio' => UploadedFile::fake()->create('speech.webm', 128, 'audio/webm'),
                'language' => 'en-US',
            ]);

        $transcribeResponse
            ->assertOk()
            ->assertJson([
                'text' => '',
            ]);

        $this->assertFalse($fakeSpeechTranscriber->wasCalled);
    }

    public function test_completed_interview_is_rendered_on_same_run_page_without_finished_redirect(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is MVC?',
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::Completed,
            'completed_at' => now(),
        ]);

        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();
        $interviewQuestion->update([
            'candidate_answer' => 'MVC separates model, view, and controller responsibilities.',
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->get(route('public-interviews.run', ['interview' => $interview]));

        $response->assertOk();
        $response->assertSee('data-interview-completed="1"', false);
        $response->assertDontSee('public-interviews.finished');
    }

    public function test_passed_interview_is_rendered_as_completed_on_run_page(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is MVC?',
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::Passed->value,
            'completed_at' => now(),
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->get(route('public-interviews.run', ['interview' => $interview]));

        $response->assertOk();
        $response->assertSee('data-interview-completed="1"', false);
    }

    public function test_enabling_public_toggle_generates_position_token(): void
    {
        $position = Position::factory()->create([
            'is_public' => false,
            'public_token' => null,
        ]);

        $position->update([
            'is_public' => true,
        ]);

        $position->refresh();

        $this->assertTrue($position->is_public);
        $this->assertNotNull($position->public_token);
        $this->assertSame(
            route('public-positions.show', ['token' => $position->public_token]),
            $position->public_url,
        );
    }

    public function test_public_post_routes_are_protected_by_rate_limiters(): void
    {
        $startRoute = Route::getRoutes()->getByName('public-positions.start');
        $transcribeRoute = Route::getRoutes()->getByName('public-interviews.transcribe');
        $answerRoute = Route::getRoutes()->getByName('public-interviews.questions.answer');

        $this->assertNotNull($startRoute);
        $this->assertNotNull($transcribeRoute);
        $this->assertNotNull($answerRoute);

        $this->assertContains('throttle:public-position-start', $startRoute->gatherMiddleware());
        $this->assertContains('throttle:public-interview-transcribe', $transcribeRoute->gatherMiddleware());
        $this->assertContains('throttle:public-interview-answer', $answerRoute->gatherMiddleware());
    }
}
