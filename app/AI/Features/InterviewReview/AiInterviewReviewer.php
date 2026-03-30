<?php

namespace App\AI\Features\InterviewReview;

use App\AI\AiProviderResolver;
use App\AI\Data\AiRequest;
use App\AI\Features\Concerns\ResolvesAiFeatureConfig;
use App\AI\Features\Concerns\ResolvesPrompt;
use App\AI\Features\InterviewReview\Contracts\InterviewReviewer;
use App\AI\Features\InterviewReview\Data\InterviewReviewResult;
use App\Models\Interview;
use App\Models\InterviewQuestion;

final class AiInterviewReviewer implements InterviewReviewer
{
    use ResolvesAiFeatureConfig, ResolvesPrompt;

    public function __construct(
        public AiProviderResolver $providerResolver,
    ) {}

    public function review(Interview $interview): InterviewReviewResult
    {
        $interview->loadMissing(['position', 'interviewQuestions.followUps']);

        $outputLanguage = $this->resolveOutputLanguage('interview_review');

        $response = $this->providerResolver
            ->resolveForFeature('interview_review')
            ->generateStructured(new AiRequest(
                systemPrompt: $this->buildSystemPrompt($outputLanguage),
                userPrompt: $this->buildUserPrompt($interview, $outputLanguage),
                jsonSchema: $this->buildJsonSchema($interview),
                schemaName: 'interview_review_result',
                model: $this->resolveFeatureModel('interview_review'),
                temperature: $this->resolveFeatureTemperature('interview_review'),
                maxTokens: $this->resolveFeatureMaxTokens('interview_review'),
            ));

        return InterviewReviewResult::fromArray($response->content);
    }

    private function buildSystemPrompt(string $outputLanguage): string
    {
        $placeholders = [
            'output_language' => $outputLanguage,
        ];

        return $this->resolvePrompt(
            'interview_review',
            'system_prompt',
            $placeholders,
        );
    }

    private function buildUserPrompt(Interview $interview, string $outputLanguage): string
    {
        $questions = $interview->interviewQuestions
            ->whereNull('parent_question_id')
            ->sortBy('sort_order')
            ->values()
            ->map(static function (InterviewQuestion $question): array {
                $data = [
                    'interview_question_id' => $question->id,
                    'sort_order' => $question->sort_order,
                    'question' => $question->question_text,
                    'evaluation_instructions' => $question->evaluation_instructions_snapshot,
                    'candidate_answer' => $question->candidate_answer,
                ];

                if ($question->followUps->isNotEmpty()) {
                    $data['follow_ups'] = $question->followUps
                        ->map(static fn (InterviewQuestion $followUp): array => [
                            'interview_question_id' => $followUp->id,
                            'follow_up_question' => $followUp->question_text,
                            'candidate_answer' => $followUp->candidate_answer,
                        ])
                        ->all();
                }

                return $data;
            })
            ->all();

        $payload = [
            'position' => [
                'id' => $interview->position_id,
                'title' => $interview->position?->title,
                'level' => $interview->position?->level?->value,
                'minimum_score' => $interview->position?->minimum_score,
            ],
            'interview' => [
                'id' => $interview->id,
                'status' => $interview->status->value,
                'started_at' => $interview->started_at?->toAtomString(),
                'completed_at' => $interview->completed_at?->toAtomString(),
            ],
            'questions' => $questions,
        ];

        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $placeholders = [
            'output_language' => $outputLanguage,
            'payload_json' => $encodedPayload,
        ];

        return $this->resolvePrompt(
            'interview_review',
            'user_prompt',
            $placeholders,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonSchema(Interview $interview): array
    {
        $questionCount = $interview->interviewQuestions->whereNull('parent_question_id')->count();

        $questionResultSchema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['interview_question_id', 'answer_score', 'adequacy_score', 'ai_comment'],
            'properties' => [
                'interview_question_id' => [
                    'type' => 'integer',
                ],
                'answer_score' => [
                    'type' => 'number',
                    'minimum' => 1,
                    'maximum' => 10,
                ],
                'adequacy_score' => [
                    'type' => 'number',
                    'minimum' => 1,
                    'maximum' => 10,
                ],
                'ai_comment' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
            ],
        ];

        $rootQuestionSchema = $questionResultSchema;
        $rootQuestionSchema['required'][] = 'follow_ups';
        $rootQuestionSchema['properties']['follow_ups'] = [
            'type' => 'array',
            'items' => $questionResultSchema,
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'questions'],
            'properties' => [
                'summary' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'questions' => [
                    'type' => 'array',
                    'minItems' => $questionCount,
                    'maxItems' => $questionCount,
                    'items' => $rootQuestionSchema,
                ],
            ],
        ];
    }
}
