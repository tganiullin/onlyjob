<?php

namespace Tests\Feature;

use App\AI\Features\FollowUpEvaluation\Contracts\FollowUpEvaluator;
use App\AI\Features\FollowUpEvaluation\Data\FollowUpEvaluationResult;
use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use App\Models\Position;
use App\Models\Question;
use App\Services\FollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FollowUpEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_created_for_weak_answer(): void
    {
        $this->fakeFollowUpEvaluator(new FollowUpEvaluationResult(
            needsFollowUp: true,
            scoreEstimate: 3.0,
            followUpQuestion: 'Расскажите подробнее о принципах SOLID.',
        ));

        $position = Position::factory()->withFollowUp(scoreThreshold: 5.0)->create();
        $interview = $this->createActiveInterview($position);
        $question = $this->createAnsweredQuestion($interview, 'Short weak answer.');

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $question);

        $this->assertInstanceOf(InterviewQuestion::class, $followUp);
        $this->assertTrue($followUp->is_follow_up);
        $this->assertSame($question->id, $followUp->parent_interview_question_id);
        $this->assertSame('Расскажите подробнее о принципах SOLID.', $followUp->question_text);
        $this->assertSame($interview->id, $followUp->interview_id);
    }

    public function test_no_follow_up_for_strong_answer(): void
    {
        $this->fakeFollowUpEvaluator(new FollowUpEvaluationResult(
            needsFollowUp: false,
            scoreEstimate: 8.0,
            followUpQuestion: null,
        ));

        $position = Position::factory()->withFollowUp(scoreThreshold: 5.0)->create();
        $interview = $this->createActiveInterview($position);
        $question = $this->createAnsweredQuestion($interview, 'Detailed comprehensive answer with examples.');

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $question);

        $this->assertNull($followUp);
    }

    public function test_follow_up_disabled_on_position(): void
    {
        $position = Position::factory()->create(['follow_up_enabled' => false]);
        $interview = $this->createActiveInterview($position);
        $question = $this->createAnsweredQuestion($interview, 'Some answer.');

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $question);

        $this->assertNull($followUp);
    }

    public function test_max_follow_ups_respected(): void
    {
        $this->fakeFollowUpEvaluator(new FollowUpEvaluationResult(
            needsFollowUp: true,
            scoreEstimate: 2.0,
            followUpQuestion: 'Another follow-up attempt.',
        ));

        $position = Position::factory()->withFollowUp(scoreThreshold: 5.0, maxPerQuestion: 1)->create();
        $interview = $this->createActiveInterview($position);
        $question = $this->createAnsweredQuestion($interview, 'Weak answer.');

        InterviewQuestion::factory()->followUp($question->id)->create([
            'interview_id' => $interview->id,
            'question_text' => 'Existing follow-up.',
            'sort_order' => $question->sort_order,
            'candidate_answer' => 'Follow-up answer.',
        ]);

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $question);

        $this->assertNull($followUp);
    }

    public function test_skipped_answer_does_not_trigger_follow_up(): void
    {
        $position = Position::factory()->withFollowUp(scoreThreshold: 5.0)->create();
        $interview = $this->createActiveInterview($position);
        $question = $this->createAnsweredQuestion($interview, 'Не знаю ответ');

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $question);

        $this->assertNull($followUp);
    }

    public function test_ai_failure_gracefully_skips_follow_up(): void
    {
        $this->app->bind(FollowUpEvaluator::class, function () {
            return new class implements FollowUpEvaluator
            {
                public function evaluate(InterviewQuestion $interviewQuestion, Position $position): FollowUpEvaluationResult
                {
                    throw new RuntimeException('AI provider unavailable.');
                }
            };
        });

        $position = Position::factory()->withFollowUp(scoreThreshold: 5.0)->create();
        $interview = $this->createActiveInterview($position);
        $question = $this->createAnsweredQuestion($interview, 'Some answer.');

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $question);

        $this->assertNull($followUp);
    }

    public function test_follow_up_returned_in_answer_endpoint(): void
    {
        $this->fakeFollowUpEvaluator(new FollowUpEvaluationResult(
            needsFollowUp: true,
            scoreEstimate: 2.5,
            followUpQuestion: 'Можете привести конкретный пример?',
        ));

        $position = Position::factory()->public()->withFollowUp(scoreThreshold: 5.0)->create();

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 1,
            'text' => 'What is SOLID?',
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'sort_order' => 2,
            'text' => 'What is DI?',
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::PendingInterview,
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => 999001,
            'telegram_chat_id' => 999001,
            'telegram_confirmed_username' => 'follow_up_test_user',
        ]);

        $firstQuestion = $interview->interviewQuestions()->orderBy('sort_order')->first();

        $response = $this->withSession(['public_interview_id' => $interview->id])
            ->postJson(
                route('public-interviews.questions.answer', [
                    'interview' => $interview,
                    'interviewQuestion' => $firstQuestion,
                ]),
                ['candidate_answer' => 'S means single.'],
            );

        $response
            ->assertOk()
            ->assertJson([
                'completed' => false,
                'next_question' => [
                    'text' => 'Можете привести конкретный пример?',
                    'is_follow_up' => true,
                ],
            ]);

        $this->assertDatabaseHas('interview_questions', [
            'interview_id' => $interview->id,
            'is_follow_up' => true,
            'parent_interview_question_id' => $firstQuestion->id,
            'question_text' => 'Можете привести конкретный пример?',
        ]);
    }

    public function test_follow_up_inherits_parent_evaluation_instructions(): void
    {
        $this->fakeFollowUpEvaluator(new FollowUpEvaluationResult(
            needsFollowUp: true,
            scoreEstimate: 2.0,
            followUpQuestion: 'Follow-up question text.',
        ));

        $position = Position::factory()->withFollowUp()->create();
        $interview = $this->createActiveInterview($position);
        $question = $this->createAnsweredQuestion($interview, 'Weak.', 'Check for SOLID knowledge.');

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $question);

        $this->assertSame('Check for SOLID knowledge.', $followUp->evaluation_instructions_snapshot);
    }

    public function test_follow_up_chain_respects_max_from_root_question(): void
    {
        $this->fakeFollowUpEvaluator(new FollowUpEvaluationResult(
            needsFollowUp: true,
            scoreEstimate: 2.0,
            followUpQuestion: 'Yet another follow-up.',
        ));

        $position = Position::factory()->withFollowUp(scoreThreshold: 5.0, maxPerQuestion: 1)->create();
        $interview = $this->createActiveInterview($position);

        $rootQuestion = InterviewQuestion::factory()->create([
            'interview_id' => $interview->id,
            'question_text' => 'Root question?',
            'sort_order' => 1,
            'candidate_answer' => 'Root answer.',
        ]);

        $firstFollowUp = InterviewQuestion::factory()->followUp($rootQuestion->id)->create([
            'interview_id' => $interview->id,
            'question_text' => 'First follow-up?',
            'sort_order' => 1,
            'candidate_answer' => 'Still weak.',
        ]);

        $followUp = app(FollowUpService::class)->evaluateAndCreateFollowUp($interview, $firstFollowUp);

        $this->assertNull($followUp);
    }

    private function fakeFollowUpEvaluator(FollowUpEvaluationResult $result): void
    {
        $this->app->bind(FollowUpEvaluator::class, function () use ($result) {
            return new class($result) implements FollowUpEvaluator
            {
                public function __construct(
                    private FollowUpEvaluationResult $result,
                ) {}

                public function evaluate(InterviewQuestion $interviewQuestion, Position $position): FollowUpEvaluationResult
                {
                    return $this->result;
                }
            };
        });
    }

    private function createActiveInterview(Position $position): Interview
    {
        return Interview::factory()->create([
            'position_id' => $position->id,
            'status' => InterviewStatus::InProgress,
            'started_at' => now(),
            'telegram_confirmed_at' => now(),
            'telegram_user_id' => fake()->randomNumber(6),
            'telegram_chat_id' => fake()->randomNumber(6),
            'telegram_confirmed_username' => fake()->userName(),
        ]);
    }

    private function createAnsweredQuestion(
        Interview $interview,
        string $answer,
        ?string $evaluationInstructions = null,
    ): InterviewQuestion {
        return InterviewQuestion::factory()->create([
            'interview_id' => $interview->id,
            'question_text' => 'Explain SOLID principles.',
            'evaluation_instructions_snapshot' => $evaluationInstructions,
            'sort_order' => 1,
            'candidate_answer' => $answer,
        ]);
    }
}
