<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Jobs\CheckInterviewJob;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use App\Models\Position;
use App\Models\Question;
use App\Services\InterviewReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Fakes\FakeAiProvider;
use Tests\TestCase;

class CheckInterviewJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_does_not_review_pending_interview(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 7,
            status: InterviewStatus::PendingInterview,
        );

        $provider = new FakeAiProvider([
            [
                'summary' => 'Should not be used',
                'questions' => [],
            ],
        ]);
        $this->useFakeAiProvider($provider);

        (new CheckInterviewJob($interview->id))->handle(app(InterviewReviewService::class));

        $this->assertSame(0, $provider->callCount);
        $this->assertSame(InterviewStatus::PendingInterview, $interview->fresh()->status);
        $this->assertNull($interview->fresh()->summary);
    }

    public function test_job_reviews_completed_interview_and_marks_as_passed(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 7,
            status: InterviewStatus::Completed,
        );

        $questions = $interview->interviewQuestions()->orderBy('sort_order')->get();
        $firstQuestion = $questions[0];
        $secondQuestion = $questions[1];

        $provider = new FakeAiProvider([
            [
                'summary' => 'Strong practical backend fundamentals with minor gaps in edge-case handling.',
                'questions' => [
                    [
                        'interview_question_id' => $firstQuestion->id,
                        'answer_score' => 8.2,
                        'adequacy_score' => 9.5,
                        'ai_comment' => 'Correctly explained the core concept and provided a relevant example.',
                    ],
                    [
                        'interview_question_id' => $secondQuestion->id,
                        'answer_score' => 7.0,
                        'adequacy_score' => 10.0,
                        'ai_comment' => 'Solid answer, but did not cover failure scenarios deeply enough.',
                    ],
                ],
            ],
        ]);
        $this->useFakeAiProvider($provider);

        (new CheckInterviewJob($interview->id))->handle(app(InterviewReviewService::class));

        $this->assertSame(1, $provider->callCount);
        $this->assertNotEmpty($provider->requests[0]->systemPrompt);
        $this->assertNotEmpty($provider->requests[0]->userPrompt);
        $this->assertStringContainsString('Russian', $provider->requests[0]->systemPrompt);
        $this->assertStringNotContainsString((string) $interview->first_name, $provider->requests[0]->userPrompt);
        $this->assertStringNotContainsString((string) $interview->last_name, $provider->requests[0]->userPrompt);
        $this->assertStringNotContainsString((string) $interview->email, $provider->requests[0]->userPrompt);

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'status' => InterviewStatus::ReviewedPassed->value,
            'summary' => 'Strong practical backend fundamentals with minor gaps in edge-case handling.',
            'score' => '7.60',
            'adequacy_score' => '9.75',
        ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $firstQuestion->id,
            'answer_score' => '8.20',
            'adequacy_score' => '9.50',
            'ai_comment' => 'Correctly explained the core concept and provided a relevant example.',
        ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $secondQuestion->id,
            'answer_score' => '7.00',
            'adequacy_score' => '10.00',
            'ai_comment' => 'Solid answer, but did not cover failure scenarios deeply enough.',
        ]);
    }

    public function test_job_reviews_completed_interview_and_marks_as_failed(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 8,
            status: InterviewStatus::Completed,
        );

        $questions = $interview->interviewQuestions()->orderBy('sort_order')->get();
        $firstQuestion = $questions[0];
        $secondQuestion = $questions[1];

        $provider = new FakeAiProvider([
            [
                'summary' => 'Candidate has basic understanding but not enough depth for the role.',
                'questions' => [
                    [
                        'interview_question_id' => $firstQuestion->id,
                        'answer_score' => 6.1,
                        'adequacy_score' => 3.0,
                        'ai_comment' => 'Partial answer with several missed core points.',
                    ],
                    [
                        'interview_question_id' => $secondQuestion->id,
                        'answer_score' => 6.9,
                        'adequacy_score' => 5.5,
                        'ai_comment' => 'Reasonable approach, but lacks confidence and completeness.',
                    ],
                ],
            ],
        ]);
        $this->useFakeAiProvider($provider);

        (new CheckInterviewJob($interview->id))->handle(app(InterviewReviewService::class));

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'status' => InterviewStatus::ReviewedFailed->value,
            'score' => '6.50',
            'adequacy_score' => '4.25',
        ]);
    }

    public function test_failed_method_sets_review_failed_status(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 7,
            status: InterviewStatus::Reviewing,
        );

        $job = new CheckInterviewJob($interview->id);
        $job->failed(new RuntimeException('AI provider timeout'));

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'status' => InterviewStatus::ReviewFailed->value,
        ]);
    }

    public function test_failed_method_does_not_overwrite_non_reviewing_status(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 7,
            status: InterviewStatus::ReviewedPassed,
        );

        $job = new CheckInterviewJob($interview->id);
        $job->failed(new RuntimeException('AI provider timeout'));

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'status' => InterviewStatus::ReviewedPassed->value,
        ]);
    }

    public function test_job_keeps_reviewing_status_on_exception_for_retry(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 7,
            status: InterviewStatus::Completed,
        );

        $this->useFakeAiProvider(new FakeAiProvider([]));

        try {
            (new CheckInterviewJob($interview->id))->handle(app(InterviewReviewService::class));
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'status' => InterviewStatus::Reviewing->value,
        ]);
    }

    public function test_job_processes_interview_in_reviewing_status_on_retry(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 7,
            status: InterviewStatus::Reviewing,
        );

        $questions = $interview->interviewQuestions()->orderBy('sort_order')->get();

        $provider = new FakeAiProvider([
            [
                'summary' => 'Retry succeeded.',
                'questions' => [
                    [
                        'interview_question_id' => $questions[0]->id,
                        'answer_score' => 8.0,
                        'adequacy_score' => 9.0,
                        'ai_comment' => 'Good answer.',
                    ],
                    [
                        'interview_question_id' => $questions[1]->id,
                        'answer_score' => 8.0,
                        'adequacy_score' => 9.0,
                        'ai_comment' => 'Good answer.',
                    ],
                ],
            ],
        ]);
        $this->useFakeAiProvider($provider);

        (new CheckInterviewJob($interview->id))->handle(app(InterviewReviewService::class));

        $this->assertSame(1, $provider->callCount);
        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'status' => InterviewStatus::ReviewedPassed->value,
            'summary' => 'Retry succeeded.',
        ]);
    }

    public function test_review_scores_follow_up_questions_individually(): void
    {
        $interview = $this->createInterviewWithAnswers(
            minimumScore: 5,
            status: InterviewStatus::Completed,
        );

        $rootQuestion = $interview->interviewQuestions()->whereNull('parent_question_id')->orderBy('sort_order')->first();

        $followUp = InterviewQuestion::query()->create([
            'interview_id' => $interview->id,
            'question_id' => null,
            'parent_question_id' => $rootQuestion->id,
            'question_text' => 'Can you give a concrete example of constructor injection?',
            'evaluation_instructions_snapshot' => $rootQuestion->evaluation_instructions_snapshot,
            'sort_order' => $rootQuestion->sort_order,
            'candidate_answer' => 'For example, injecting a UserRepository into a controller constructor.',
        ]);

        $questions = $interview->interviewQuestions()->whereNull('parent_question_id')->orderBy('sort_order')->get();

        $provider = new FakeAiProvider([
            [
                'summary' => 'Good understanding with follow-up improvement.',
                'questions' => [
                    [
                        'interview_question_id' => $questions[0]->id,
                        'answer_score' => 8.5,
                        'adequacy_score' => 10.0,
                        'ai_comment' => 'Good answer, improved with follow-up.',
                        'follow_ups' => [
                            [
                                'interview_question_id' => $followUp->id,
                                'answer_score' => 7.0,
                                'adequacy_score' => 9.0,
                                'ai_comment' => 'Concrete example demonstrates practical understanding.',
                            ],
                        ],
                    ],
                    [
                        'interview_question_id' => $questions[1]->id,
                        'answer_score' => 7.0,
                        'adequacy_score' => 10.0,
                        'ai_comment' => 'Solid answer.',
                    ],
                ],
            ],
        ]);
        $this->useFakeAiProvider($provider);

        (new CheckInterviewJob($interview->id))->handle(app(InterviewReviewService::class));

        $this->assertSame(1, $provider->callCount);

        $userPrompt = $provider->requests[0]->userPrompt;
        $this->assertStringContainsString('Can you give a concrete example of constructor injection?', $userPrompt);
        $this->assertStringContainsString((string) $followUp->id, $userPrompt);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $followUp->id,
            'answer_score' => '7.00',
            'adequacy_score' => '9.00',
            'ai_comment' => 'Concrete example demonstrates practical understanding.',
        ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $rootQuestion->id,
            'answer_score' => '8.50',
            'ai_comment' => 'Good answer, improved with follow-up.',
        ]);

        $freshInterview = $interview->fresh();
        $this->assertSame(InterviewStatus::ReviewedPassed, $freshInterview->status);
        $expectedScore = round((8.5 + 7.0 + 7.0) / 3, 2);
        $this->assertEquals($expectedScore, (float) $freshInterview->score);
    }

    private function useFakeAiProvider(FakeAiProvider $provider): void
    {
        config()->set('ai.default_provider', 'fake');
        config()->set('ai.providers.fake', FakeAiProvider::class);
        config()->set('ai.features.interview_review.provider', 'fake');
        app()->instance(FakeAiProvider::class, $provider);
    }

    private function createInterviewWithAnswers(int $minimumScore, InterviewStatus $status): Interview
    {
        $position = Position::factory()->create([
            'minimum_score' => $minimumScore,
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'Explain service container benefits.',
            'evaluation_instructions' => 'Look for practical Laravel examples.',
            'sort_order' => 1,
        ]);

        Question::factory()->create([
            'position_id' => $position->id,
            'text' => 'How would you handle DB transaction failures?',
            'evaluation_instructions' => 'Check understanding of rollback and retries.',
            'sort_order' => 2,
        ]);

        $interview = Interview::factory()->create([
            'position_id' => $position->id,
            'status' => $status->value,
            'summary' => null,
            'score' => null,
            'completed_at' => $status === InterviewStatus::Completed ? now() : null,
        ]);

        $interviewQuestions = $interview->interviewQuestions()->orderBy('sort_order')->get();

        $interviewQuestions[0]->update([
            'candidate_answer' => 'Container helps resolve dependencies and keeps services decoupled.',
        ]);
        $interviewQuestions[1]->update([
            'candidate_answer' => 'I wrap critical changes in transaction and roll back on exception.',
        ]);

        return $interview->fresh(['position', 'interviewQuestions']);
    }
}
