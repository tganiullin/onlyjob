<?php

namespace Tests\Feature;

use App\AI\Features\SpeechToText\Contracts\SpeechTranscriber;
use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\InterviewTelegramConfirmation;
use App\Models\Position;
use App\Models\PositionCompanyQuestion;
use App\Models\Question;
use App\Services\TelegramAccountConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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

    public function test_public_position_entry_contains_logo_url(): void
    {
        $position = Position::factory()->public()->create([
            'public_token' => 'public-position-token-with-logo',
        ]);

        $response = $this->get(route('public-positions.show', ['token' => $position->public_token]));

        $response->assertOk();
        $response->assertSee('data-logo-url="'.asset('images/logo.svg').'"', false);
    }

    public function test_start_creates_pending_confirmation_flow_without_creating_interview(): void
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

        $response = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'telegram' => '@John_Doe',
            'client_request_id' => '95d22f4d-d852-4f30-8266-9a7f3dbdc18a',
            'consent' => '1',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'pending_confirmation',
            ])
            ->assertJsonStructure([
                'status',
                'status_token',
                'status_endpoint',
                'telegram_deeplink',
            ]);

        $statusToken = $response->json('status_token');

        $this->assertNotEmpty($statusToken);
        $this->assertSame($statusToken, session('public_pending_confirmation_status_token'));

        $this->assertDatabaseHas('interview_telegram_confirmations', [
            'position_id' => $position->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'expected_username' => 'john_doe',
            'status_token' => $statusToken,
            'interview_id' => null,
        ]);

        $this->assertDatabaseCount('interviews', 0);
        $this->assertDatabaseCount('interview_questions', 0);
    }

    public function test_start_requires_telegram_account(): void
    {
        $position = Position::factory()->public()->create([
            'public_token' => 'public-position-token',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
        ]);

        $response = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'client_request_id' => 'f960250f-9a0d-4fdf-83ff-608f668793c4',
            'consent' => '1',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telegram']);
    }

    public function test_start_reuses_pending_flow_for_same_client_request_id(): void
    {
        $position = Position::factory()->public()->create([
            'public_token' => 'public-position-token',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
        ]);

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'telegram' => '@john_doe',
            'client_request_id' => '1f6d2ccf-5bc0-4638-8ecc-699cfbe99d16',
            'consent' => '1',
        ];

        $firstResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), $payload);
        $secondResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), $payload);

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $this->assertSame(
            $firstResponse->json('status_token'),
            $secondResponse->json('status_token'),
        );

        $this->assertDatabaseCount('interview_telegram_confirmations', 1);
    }

    public function test_start_reuses_pending_flow_for_same_candidate_data_with_new_client_request_id(): void
    {
        $position = Position::factory()->public()->create([
            'public_token' => 'public-position-token',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
        ]);

        $firstResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'telegram' => '@john_doe',
            'client_request_id' => '33a4f2ab-f241-445f-a9af-6b84cb24ce4b',
            'consent' => '1',
        ]);

        $secondResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'telegram' => '@john_doe',
            'client_request_id' => '0050beca-8c03-4e97-8f2d-f128194ee044',
            'consent' => '1',
        ]);

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $this->assertSame(
            $firstResponse->json('status_token'),
            $secondResponse->json('status_token'),
        );

        $this->assertDatabaseCount('interview_telegram_confirmations', 1);
    }

    public function test_run_endpoint_is_forbidden_when_interview_is_not_telegram_confirmed(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is MVC?',
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'telegram_confirmed_at' => null,
            'telegram_user_id' => null,
            'telegram_chat_id' => null,
            'telegram_confirmed_username' => null,
        ]);

        $this->withSession(['public_interview_id' => $interview->id])
            ->get(route('public-interviews.run', ['interview' => $interview]))
            ->assertForbidden();
    }

    public function test_confirmation_status_promotes_session_after_telegram_confirmation(): void
    {
        $position = Position::factory()->public()->create([
            'public_token' => 'public-position-token',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'First question?',
        ]);

        $startResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'telegram' => '@jane_smith',
            'client_request_id' => '4ad30165-e88e-494e-ab5f-7560f8bf5f37',
            'consent' => '1',
        ]);

        $startResponse->assertOk()->assertJson(['status' => 'pending_confirmation']);

        $statusToken = (string) $startResponse->json('status_token');

        app(TelegramAccountConfirmationService::class)->confirmByTokenAndUsername($statusToken, [
            'username' => 'jane_smith',
            'user_id' => 111111,
            'chat_id' => 111111,
            'chat_type' => 'private',
            'update_id' => 222222,
        ]);

        $statusResponse = $this->getJson(route('public-positions.confirmation-status', [
            'token' => $position->public_token,
            'statusToken' => $statusToken,
        ]));

        $statusResponse
            ->assertOk()
            ->assertJson([
                'status' => 'confirmed',
            ])
            ->assertJsonStructure(['redirect']);

        $interview = Interview::query()->firstOrFail();
        $this->assertSame($interview->id, session('public_interview_id'));
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

        $interview = $this->startAndConfirmInterview($position, [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'telegram' => '@jane_smith',
        ])->load('interviewQuestions');

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

        $interview = $this->startAndConfirmInterview($position, [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'telegram' => '@jane_smith',
        ])->load('interviewQuestions');

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

        $interview = $this->startAndConfirmInterview($position, [
            'first_name' => 'Nora',
            'last_name' => 'Hall',
            'telegram' => '@nora_hall',
        ]);

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
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 123456,
            'telegram_chat_id' => 123456,
            'telegram_confirmed_username' => 'terminal_user',
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

    public function test_feedback_endpoint_saves_candidate_rating_after_interview_completion(): void
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
            'candidate_feedback_rating' => null,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 523456,
            'telegram_chat_id' => 523456,
            'telegram_confirmed_username' => 'feedback_user',
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(route('public-interviews.feedback', ['interview' => $interview]), [
                'candidate_feedback_rating' => 5,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'candidate_feedback_rating' => 5,
            ]);

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'candidate_feedback_rating' => 5,
        ]);
    }

    public function test_feedback_endpoint_validates_rating_range(): void
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
            'candidate_feedback_rating' => null,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 623456,
            'telegram_chat_id' => 623456,
            'telegram_confirmed_username' => 'feedback_validation_user',
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(route('public-interviews.feedback', ['interview' => $interview]), [
                'candidate_feedback_rating' => 6,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['candidate_feedback_rating']);
    }

    public function test_feedback_endpoint_is_forbidden_without_active_public_interview_session(): void
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
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 723456,
            'telegram_chat_id' => 723456,
            'telegram_confirmed_username' => 'feedback_forbidden_user',
        ]);

        $this->withSession(['public_interview_id' => null])
            ->postJson(route('public-interviews.feedback', ['interview' => $interview]), [
                'candidate_feedback_rating' => 4,
            ])
            ->assertForbidden();
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
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 223456,
            'telegram_chat_id' => 223456,
            'telegram_confirmed_username' => 'completed_user',
        ]);

        $interviewQuestion = $interview->interviewQuestions()->firstOrFail();
        $interviewQuestion->update([
            'candidate_answer' => 'MVC separates model, view, and controller responsibilities.',
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->get(route('public-interviews.run', ['interview' => $interview]));

        $response->assertOk();
        $response->assertSee('data-interview-completed="1"', false);
        $response->assertSee('data-logo-url="'.asset('images/logo.svg').'"', false);
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
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 323456,
            'telegram_chat_id' => 323456,
            'telegram_confirmed_username' => 'passed_user',
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->get(route('public-interviews.run', ['interview' => $interview]));

        $response->assertOk();
        $response->assertSee('data-interview-completed="1"', false);
    }

    public function test_run_page_contains_company_questions_sorted_by_order(): void
    {
        $position = Position::factory()->public()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is MVC?',
        ]);

        PositionCompanyQuestion::factory()->create([
            'position_id' => $position->id,
            'question' => 'Есть ли испытательный срок?',
            'answer' => 'Да, испытательный срок составляет 3 месяца.',
            'sort_order' => 2,
        ]);

        PositionCompanyQuestion::factory()->create([
            'position_id' => $position->id,
            'question' => 'Есть ли удаленный формат?',
            'answer' => 'Да, гибридный формат: 2 дня офис, 3 дня удаленно.',
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::Completed,
            'completed_at' => now(),
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 423456,
            'telegram_chat_id' => 423456,
            'telegram_confirmed_username' => 'company_questions_user',
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->get(route('public-interviews.run', ['interview' => $interview]));

        $response->assertOk();
        $response->assertViewHas('companyQuestions', static function (array $companyQuestions): bool {
            return count($companyQuestions) === 2
                && $companyQuestions[0]['question'] === 'Есть ли удаленный формат?'
                && $companyQuestions[1]['question'] === 'Есть ли испытательный срок?';
        });
    }

    public function test_company_questions_sort_order_is_assigned_incrementally_when_missing(): void
    {
        $position = Position::factory()->create();

        $firstQuestion = PositionCompanyQuestion::query()->create([
            'position_id' => $position->id,
            'question' => 'Первый вопрос',
            'answer' => 'Первый ответ',
        ]);

        $secondQuestion = PositionCompanyQuestion::query()->create([
            'position_id' => $position->id,
            'question' => 'Второй вопрос',
            'answer' => 'Второй ответ',
        ]);

        $this->assertSame(1, $firstQuestion->sort_order);
        $this->assertSame(2, $secondQuestion->sort_order);
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
        $confirmationStatusRoute = Route::getRoutes()->getByName('public-positions.confirmation-status');
        $transcribeRoute = Route::getRoutes()->getByName('public-interviews.transcribe');
        $answerRoute = Route::getRoutes()->getByName('public-interviews.questions.answer');
        $feedbackRoute = Route::getRoutes()->getByName('public-interviews.feedback');

        $this->assertNotNull($startRoute);
        $this->assertNotNull($confirmationStatusRoute);
        $this->assertNotNull($transcribeRoute);
        $this->assertNotNull($answerRoute);
        $this->assertNotNull($feedbackRoute);

        $this->assertContains('throttle:public-position-start', $startRoute->gatherMiddleware());
        $this->assertContains('throttle:public-interview-confirmation-status', $confirmationStatusRoute->gatherMiddleware());
        $this->assertContains('throttle:public-interview-transcribe', $transcribeRoute->gatherMiddleware());
        $this->assertContains('throttle:public-interview-answer', $answerRoute->gatherMiddleware());
        $this->assertContains('throttle:public-interview-answer', $feedbackRoute->gatherMiddleware());
    }

    /**
     * @param  array{first_name?: string, last_name?: string, telegram?: string}  $payload
     */
    private function startAndConfirmInterview(Position $position, array $payload = []): Interview
    {
        $payload = array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'telegram' => '@jane_smith',
            'client_request_id' => (string) Str::uuid(),
            'consent' => '1',
        ], $payload);

        $startResponse = $this->postJson(route('public-positions.start', ['token' => $position->public_token]), $payload);

        $startResponse
            ->assertOk()
            ->assertJson([
                'status' => 'pending_confirmation',
            ]);

        $statusToken = (string) $startResponse->json('status_token');
        $this->assertNotSame('', $statusToken);

        $telegramUsername = strtolower(ltrim((string) $payload['telegram'], '@'));

        $confirmationResult = app(TelegramAccountConfirmationService::class)->confirmByTokenAndUsername(
            $statusToken,
            [
                'username' => $telegramUsername,
                'user_id' => 770000 + random_int(1, 9999),
                'chat_id' => 880000 + random_int(1, 9999),
                'chat_type' => 'private',
                'update_id' => 990000 + random_int(1, 9999),
            ],
        );

        $this->assertContains($confirmationResult['status'], ['confirmed', 'already_confirmed']);

        $statusResponse = $this->getJson(route('public-positions.confirmation-status', [
            'token' => $position->public_token,
            'statusToken' => $statusToken,
        ]));

        $statusResponse
            ->assertOk()
            ->assertJson([
                'status' => 'confirmed',
            ]);

        $interviewId = InterviewTelegramConfirmation::query()
            ->where('status_token', $statusToken)
            ->value('interview_id');

        $this->assertNotNull($interviewId);

        return Interview::query()->findOrFail($interviewId);
    }
}
