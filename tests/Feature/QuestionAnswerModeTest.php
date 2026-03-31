<?php

namespace Tests\Feature;

use App\Enums\QuestionAnswerMode;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use App\Models\InterviewTelegramConfirmation;
use App\Models\Position;
use App\Models\Question;
use App\Services\TelegramAccountConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuestionAnswerModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_factory_defaults_to_voice_mode(): void
    {
        $question = Question::factory()->create();

        $this->assertSame(QuestionAnswerMode::Voice, $question->answer_mode);
    }

    public function test_question_factory_text_mode_state(): void
    {
        $question = Question::factory()->textMode()->create();

        $this->assertSame(QuestionAnswerMode::Text, $question->answer_mode);
    }

    public function test_interview_question_factory_defaults_to_voice_mode(): void
    {
        $interviewQuestion = InterviewQuestion::factory()->create();

        $this->assertSame(QuestionAnswerMode::Voice, $interviewQuestion->answer_mode);
    }

    public function test_interview_question_factory_text_mode_state(): void
    {
        $interviewQuestion = InterviewQuestion::factory()->textMode()->create();

        $this->assertSame(QuestionAnswerMode::Text, $interviewQuestion->answer_mode);
    }

    public function test_snapshot_copies_answer_mode_from_questions(): void
    {
        $position = Position::factory()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'answer_mode' => QuestionAnswerMode::Voice,
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'answer_mode' => QuestionAnswerMode::Text,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
        ]);

        $interviewQuestions = $interview->interviewQuestions()
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $interviewQuestions);
        $this->assertSame(QuestionAnswerMode::Voice, $interviewQuestions[0]->answer_mode);
        $this->assertSame(QuestionAnswerMode::Text, $interviewQuestions[1]->answer_mode);
    }

    public function test_run_page_includes_answer_mode_in_questions_json(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'Voice question?',
            'answer_mode' => QuestionAnswerMode::Voice,
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'Text question?',
            'answer_mode' => QuestionAnswerMode::Text,
        ]);

        $interview = $this->startAndConfirmInterview($position);

        $response = $this->get(route('public-interviews.run', ['interview' => $interview]));

        $response->assertOk();
        $response->assertViewHas('questions', static function (array $questions): bool {
            return count($questions) === 2
                && $questions[0]['answer_mode'] === 'voice'
                && $questions[1]['answer_mode'] === 'text';
        });
    }

    public function test_text_mode_question_allows_answer_without_audio(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'answer_mode' => QuestionAnswerMode::Text,
        ]);

        $interview = $this->startAndConfirmInterview($position)->load('interviewQuestions');
        $interviewQuestion = $interview->interviewQuestions->first();

        $response = $this->postJson(
            route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]),
            ['candidate_answer' => 'My typed answer.'],
        );

        $response->assertOk();

        $this->assertDatabaseHas('interview_questions', [
            'id' => $interviewQuestion->id,
            'candidate_answer' => 'My typed answer.',
        ]);
    }

    public function test_voice_mode_question_rejects_answer_without_audio(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'answer_mode' => QuestionAnswerMode::Voice,
        ]);

        $interview = $this->startAndConfirmInterview($position)->load('interviewQuestions');
        $interviewQuestion = $interview->interviewQuestions->first();

        $response = $this->postJson(
            route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]),
            ['candidate_answer' => 'Trying to submit text for voice question.'],
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('interview_questions', [
            'id' => $interviewQuestion->id,
            'candidate_answer' => null,
        ]);
    }

    public function test_voice_mode_question_accepts_answer_when_audio_was_uploaded(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'answer_mode' => QuestionAnswerMode::Voice,
        ]);

        $interview = $this->startAndConfirmInterview($position)->load('interviewQuestions');
        $interviewQuestion = $interview->interviewQuestions->first();

        $interviewQuestion->forceFill([
            'candidate_answer_audio_path' => 'interview-audio/1/1.webm',
        ])->save();

        $response = $this->postJson(
            route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]),
            ['candidate_answer' => 'Transcribed voice answer.'],
        );

        $response->assertOk();

        $this->assertDatabaseHas('interview_questions', [
            'id' => $interviewQuestion->id,
            'candidate_answer' => 'Transcribed voice answer.',
        ]);
    }

    public function test_skip_endpoint_saves_skip_answer_and_advances(): void
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

        $interview = $this->startAndConfirmInterview($position)->load('interviewQuestions');
        $firstQuestion = $interview->interviewQuestions->sortBy('sort_order')->values()[0];
        $secondQuestion = $interview->interviewQuestions->sortBy('sort_order')->values()[1];

        $response = $this->postJson(
            route('public-interviews.questions.skip', [
                'interview' => $interview,
                'interviewQuestion' => $firstQuestion,
            ]),
        );

        $response
            ->assertOk()
            ->assertJson([
                'completed' => false,
                'next_question' => [
                    'id' => $secondQuestion->id,
                ],
            ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $firstQuestion->id,
            'candidate_answer' => 'Не знаю ответ',
        ]);
    }

    public function test_skip_endpoint_works_for_voice_mode_questions_without_audio(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'answer_mode' => QuestionAnswerMode::Voice,
        ]);

        $interview = $this->startAndConfirmInterview($position)->load('interviewQuestions');
        $interviewQuestion = $interview->interviewQuestions->first();

        $response = $this->postJson(
            route('public-interviews.questions.skip', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]),
        );

        $response
            ->assertOk()
            ->assertJson(['completed' => true]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $interviewQuestion->id,
            'candidate_answer' => 'Не знаю ответ',
        ]);
    }

    public function test_skip_endpoint_does_not_trigger_follow_up(): void
    {
        $position = Position::factory()->public()->create([
            'follow_up_enabled' => true,
            'max_follow_ups_per_question' => 2,
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = $this->startAndConfirmInterview($position)->load('interviewQuestions');
        $interviewQuestion = $interview->interviewQuestions->first();

        $response = $this->postJson(
            route('public-interviews.questions.skip', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]),
        );

        $response->assertOk();
        $response->assertJsonMissing(['follow_up_check']);
    }

    public function test_skip_endpoint_is_forbidden_without_session(): void
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
            ->postJson(route('public-interviews.questions.skip', [
                'interview' => $interview,
                'interviewQuestion' => $interviewQuestion,
            ]))
            ->assertForbidden();
    }

    public function test_skip_route_has_rate_limiter(): void
    {
        $skipRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('public-interviews.questions.skip');

        $this->assertNotNull($skipRoute);
        $this->assertContains('throttle:public-interview-answer', $skipRoute->gatherMiddleware());
    }

    public function test_next_question_response_includes_answer_mode(): void
    {
        $position = Position::factory()->public()->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'answer_mode' => QuestionAnswerMode::Voice,
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'answer_mode' => QuestionAnswerMode::Text,
        ]);

        $interview = $this->startAndConfirmInterview($position)->load('interviewQuestions');
        $firstQuestion = $interview->interviewQuestions->sortBy('sort_order')->values()[0];

        $firstQuestion->forceFill([
            'candidate_answer_audio_path' => 'interview-audio/1/1.webm',
        ])->save();

        $response = $this->postJson(
            route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $firstQuestion,
            ]),
            ['candidate_answer' => 'My answer.'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('next_question.answer_mode', 'text');
    }

    public function test_is_voice_mode_helper(): void
    {
        $voiceQuestion = InterviewQuestion::factory()->create([
            'answer_mode' => QuestionAnswerMode::Voice,
        ]);

        $textQuestion = InterviewQuestion::factory()->create([
            'answer_mode' => QuestionAnswerMode::Text,
        ]);

        $this->assertTrue($voiceQuestion->isVoiceMode());
        $this->assertFalse($textQuestion->isVoiceMode());
    }

    public function test_has_audio_recording_helper(): void
    {
        $withAudio = InterviewQuestion::factory()->create([
            'candidate_answer_audio_path' => 'interview-audio/1/1.webm',
        ]);

        $withoutAudio = InterviewQuestion::factory()->create([
            'candidate_answer_audio_path' => null,
        ]);

        $this->assertTrue($withAudio->hasAudioRecording());
        $this->assertFalse($withoutAudio->hasAudioRecording());
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
            ->assertJson(['status' => 'pending_confirmation']);

        $statusToken = (string) $startResponse->json('status_token');

        $telegramUsername = strtolower(ltrim((string) $payload['telegram'], '@'));

        app(TelegramAccountConfirmationService::class)->confirmByTokenAndUsername(
            $statusToken,
            [
                'username' => $telegramUsername,
                'user_id' => 770000 + random_int(1, 9999),
                'chat_id' => 880000 + random_int(1, 9999),
                'chat_type' => 'private',
                'update_id' => 990000 + random_int(1, 9999),
            ],
        );

        $this->getJson(route('public-positions.confirmation-status', [
            'token' => $position->public_token,
            'statusToken' => $statusToken,
        ]));

        $interviewId = InterviewTelegramConfirmation::query()
            ->where('status_token', $statusToken)
            ->value('interview_id');

        return Interview::query()->findOrFail($interviewId);
    }
}
