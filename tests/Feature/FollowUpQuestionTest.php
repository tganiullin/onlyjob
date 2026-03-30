<?php

namespace Tests\Feature;

use App\AI\Features\FollowUpGeneration\Contracts\FollowUpGenerator;
use App\AI\Features\FollowUpGeneration\Data\FollowUpResult;
use App\Enums\InterviewStatus;
use App\Jobs\GenerateFollowUpJob;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use App\Models\Position;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FollowUpQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_dispatches_follow_up_job_when_enabled(): void
    {
        Queue::fake();

        $position = Position::factory()->withFollowUp()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is dependency injection?',
        ]);
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'What is ORM?',
        ]);

        $interview = $this->createConfirmedInterview($position);
        $firstQuestion = $interview->interviewQuestions()->orderBy('sort_order')->first();

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $firstQuestion,
            ]), [
                'candidate_answer' => 'DI is a pattern where dependencies are provided externally.',
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'completed',
                'follow_up_check' => ['key', 'status_url'],
            ])
            ->assertJson(['completed' => false]);

        Queue::assertPushed(GenerateFollowUpJob::class);
    }

    public function test_answer_returns_next_question_when_follow_up_disabled(): void
    {
        Queue::fake();

        $position = Position::factory()->create(['follow_up_enabled' => false]);
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is dependency injection?',
        ]);
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'What is ORM?',
        ]);

        $interview = $this->createConfirmedInterview($position);
        $firstQuestion = $interview->interviewQuestions()->orderBy('sort_order')->first();

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $firstQuestion,
            ]), [
                'candidate_answer' => 'DI is a pattern.',
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['completed', 'next_question'])
            ->assertJsonMissing(['follow_up_check']);

        Queue::assertNotPushed(GenerateFollowUpJob::class);
    }

    public function test_skip_answer_does_not_trigger_follow_up(): void
    {
        Queue::fake();

        $position = Position::factory()->withFollowUp()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is dependency injection?',
        ]);
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'What is ORM?',
        ]);

        $interview = $this->createConfirmedInterview($position);
        $firstQuestion = $interview->interviewQuestions()->orderBy('sort_order')->first();

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $firstQuestion,
            ]), [
                'candidate_answer' => 'Не знаю ответ',
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['next_question']);
        $response->assertJsonMissing(['follow_up_check']);

        Queue::assertNotPushed(GenerateFollowUpJob::class);
    }

    public function test_follow_up_status_returns_cache_data(): void
    {
        $position = Position::factory()->withFollowUp()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = $this->createConfirmedInterview($position);
        $key = 'test-follow-up-key';

        Cache::put("follow_up:{$key}", [
            'status' => 'completed',
            'needs_follow_up' => true,
            'follow_up' => [
                'id' => 999,
                'question_text' => 'Can you elaborate on that?',
            ],
        ], now()->addMinutes(10));

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->getJson(route('public-interviews.follow-up-status', [
                'interview' => $interview,
                'key' => $key,
            ]));

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'completed',
                'needs_follow_up' => true,
                'follow_up' => [
                    'id' => 999,
                    'question_text' => 'Can you elaborate on that?',
                ],
            ]);
    }

    public function test_follow_up_status_returns_next_question_when_no_follow_up_needed(): void
    {
        $position = Position::factory()->withFollowUp()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'Second question?',
        ]);

        $interview = $this->createConfirmedInterview($position);
        $questions = $interview->interviewQuestions()->orderBy('sort_order')->get();
        $questions[0]->update(['candidate_answer' => 'My answer']);

        $key = 'no-follow-up-key';
        Cache::put("follow_up:{$key}", [
            'status' => 'completed',
            'needs_follow_up' => false,
        ], now()->addMinutes(10));

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->getJson(route('public-interviews.follow-up-status', [
                'interview' => $interview,
                'key' => $key,
            ]));

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'completed',
                'needs_follow_up' => false,
            ])
            ->assertJsonStructure(['next_question']);
    }

    public function test_follow_up_status_returns_404_for_unknown_key(): void
    {
        $position = Position::factory()->withFollowUp()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = $this->createConfirmedInterview($position);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->getJson(route('public-interviews.follow-up-status', [
                'interview' => $interview,
                'key' => 'non-existent-key',
            ]));

        $response->assertNotFound();
    }

    public function test_generate_follow_up_job_creates_follow_up_question(): void
    {
        $position = Position::factory()->withFollowUp(1, 5)->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is SOLID?',
            'evaluation_instructions' => 'Check understanding of all 5 principles.',
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::InProgress,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 111111,
            'telegram_chat_id' => 111111,
            'telegram_confirmed_username' => 'test_user',
        ]);

        $question = $interview->interviewQuestions()->first();
        $question->update(['candidate_answer' => 'SOLID is about writing clean code.']);

        $this->app->bind(FollowUpGenerator::class, function () {
            return new class implements FollowUpGenerator
            {
                public function generate(InterviewQuestion $question, ?int $minScore = null): FollowUpResult
                {
                    return new FollowUpResult(
                        needsFollowUp: true,
                        followUpQuestion: 'Can you name at least 3 of the SOLID principles?',
                    );
                }
            };
        });

        $key = 'job-test-key';
        Cache::put("follow_up:{$key}", ['status' => 'processing'], now()->addMinutes(10));

        $job = new GenerateFollowUpJob($question->id, $key);
        $job->handle(app(FollowUpGenerator::class));

        $this->assertDatabaseHas('interview_questions', [
            'interview_id' => $interview->id,
            'parent_question_id' => $question->id,
            'question_text' => 'Can you name at least 3 of the SOLID principles?',
            'candidate_answer' => null,
        ]);

        $cached = Cache::get("follow_up:{$key}");
        $this->assertSame('completed', $cached['status']);
        $this->assertTrue($cached['needs_follow_up']);
        $this->assertNotNull($cached['follow_up']['id']);
    }

    public function test_generate_follow_up_job_skips_when_limit_reached(): void
    {
        $position = Position::factory()->withFollowUp(1, 5)->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is SOLID?',
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::InProgress,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 222222,
            'telegram_chat_id' => 222222,
            'telegram_confirmed_username' => 'limit_user',
        ]);

        $question = $interview->interviewQuestions()->first();
        $question->update(['candidate_answer' => 'SOLID is about clean code.']);

        InterviewQuestion::factory()->followUp($question->id)->create([
            'interview_id' => $interview->id,
            'question_text' => 'Existing follow-up',
            'sort_order' => $question->sort_order,
            'candidate_answer' => 'My follow-up answer.',
        ]);

        $key = 'limit-test-key';
        Cache::put("follow_up:{$key}", ['status' => 'processing'], now()->addMinutes(10));

        $job = new GenerateFollowUpJob($question->id, $key);
        $job->handle(app(FollowUpGenerator::class));

        $cached = Cache::get("follow_up:{$key}");
        $this->assertSame('completed', $cached['status']);
        $this->assertFalse($cached['needs_follow_up']);

        $this->assertSame(1, InterviewQuestion::query()->where('parent_question_id', $question->id)->count());
    }

    public function test_generate_follow_up_job_skips_for_empty_answer(): void
    {
        $position = Position::factory()->withFollowUp()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::InProgress,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 333333,
            'telegram_chat_id' => 333333,
            'telegram_confirmed_username' => 'empty_user',
        ]);

        $question = $interview->interviewQuestions()->first();
        $question->update(['candidate_answer' => '']);

        $key = 'empty-answer-key';
        Cache::put("follow_up:{$key}", ['status' => 'processing'], now()->addMinutes(10));

        $job = new GenerateFollowUpJob($question->id, $key);
        $job->handle(app(FollowUpGenerator::class));

        $cached = Cache::get("follow_up:{$key}");
        $this->assertSame('completed', $cached['status']);
        $this->assertFalse($cached['needs_follow_up']);

        $this->assertSame(0, InterviewQuestion::query()->where('parent_question_id', $question->id)->count());
    }

    public function test_follow_up_question_is_answered_via_standard_answer_endpoint(): void
    {
        Queue::fake();

        $position = Position::factory()->withFollowUp()->create();
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is SOLID?',
        ]);
        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'What is ORM?',
        ]);

        $interview = $this->createConfirmedInterview($position);
        $questions = $interview->interviewQuestions()->orderBy('sort_order')->orderBy('id')->get();
        $mainQuestion = $questions[0];

        $mainQuestion->update(['candidate_answer' => 'SOLID is about clean code.']);

        $followUp = InterviewQuestion::factory()->followUp($mainQuestion->id)->create([
            'interview_id' => $interview->id,
            'question_text' => 'Can you name the S and O principles?',
            'sort_order' => $mainQuestion->sort_order,
        ]);

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(route('public-interviews.questions.answer', [
                'interview' => $interview,
                'interviewQuestion' => $followUp,
            ]), [
                'candidate_answer' => 'S is Single Responsibility, O is Open/Closed.',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('interview_questions', [
            'id' => $followUp->id,
            'candidate_answer' => 'S is Single Responsibility, O is Open/Closed.',
        ]);
    }

    public function test_follow_up_status_route_has_rate_limiter(): void
    {
        $route = Route::getRoutes()->getByName('public-interviews.follow-up-status');

        $this->assertNotNull($route);
        $this->assertContains('throttle:public-interview-follow-up-status', $route->gatherMiddleware());
    }

    public function test_interview_question_follow_up_relations(): void
    {
        $position = Position::factory()->create();
        $interview = Interview::factory()->create(['position_id' => $position->id]);

        $mainQuestion = InterviewQuestion::factory()->create([
            'interview_id' => $interview->id,
            'sort_order' => 1,
        ]);

        $followUp = InterviewQuestion::factory()->followUp($mainQuestion->id)->create([
            'interview_id' => $interview->id,
            'sort_order' => 1,
        ]);

        $this->assertTrue($followUp->isFollowUp());
        $this->assertFalse($mainQuestion->isFollowUp());

        $this->assertSame($mainQuestion->id, $followUp->resolveRootQuestionId());
        $this->assertSame($mainQuestion->id, $mainQuestion->resolveRootQuestionId());

        $this->assertSame(1, $mainQuestion->followUps()->count());
        $this->assertSame($mainQuestion->id, $followUp->parentQuestion->id);
    }

    public function test_follow_up_result_dto_validates_correctly(): void
    {
        $result = FollowUpResult::fromArray([
            'needs_follow_up' => true,
            'follow_up_question' => 'Can you elaborate?',
        ]);

        $this->assertTrue($result->needsFollowUp);
        $this->assertSame('Can you elaborate?', $result->followUpQuestion);

        $noFollowUp = FollowUpResult::fromArray([
            'needs_follow_up' => false,
            'follow_up_question' => null,
        ]);

        $this->assertFalse($noFollowUp->needsFollowUp);
        $this->assertNull($noFollowUp->followUpQuestion);
    }

    private function createConfirmedInterview(Position $position): Interview
    {
        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::PendingInterview,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => random_int(100000, 999999),
            'telegram_chat_id' => random_int(100000, 999999),
            'telegram_confirmed_username' => 'test_user_'.random_int(1, 9999),
        ]);

        return $interview;
    }
}
