<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Jobs\CheckInterviewJob;
use App\Models\Interview;
use App\Models\Position;
use App\Models\Question;
use App\Services\InterviewReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                        'ai_comment' => 'Correctly explained the core concept and provided a relevant example.',
                    ],
                    [
                        'interview_question_id' => $secondQuestion->id,
                        'answer_score' => 7.0,
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
        ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $firstQuestion->id,
            'answer_score' => '8.20',
            'ai_comment' => 'Correctly explained the core concept and provided a relevant example.',
        ]);

        $this->assertDatabaseHas('interview_questions', [
            'id' => $secondQuestion->id,
            'answer_score' => '7.00',
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
                        'ai_comment' => 'Partial answer with several missed core points.',
                    ],
                    [
                        'interview_question_id' => $secondQuestion->id,
                        'answer_score' => 6.9,
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
        ]);
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
