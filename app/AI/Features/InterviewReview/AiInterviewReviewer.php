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

        $response = $this->providerResolver
            ->resolveForFeature('interview_review')
            ->generateStructured(new AiRequest(
                systemPrompt: $this->buildSystemPrompt(),
                userPrompt: $this->buildUserPrompt($interview),
                jsonSchema: $this->buildJsonSchema($interview),
                schemaName: 'interview_review_result',
                model: $this->resolveFeatureModel('interview_review'),
                temperature: $this->resolveFeatureTemperature('interview_review'),
                maxTokens: $this->resolveFeatureMaxTokens('interview_review'),
            ));

        return InterviewReviewResult::fromArray($response->content);
    }

    private function buildSystemPrompt(): string
    {
        $placeholders = [
            'output_language' => $this->resolveOutputLanguage(),
        ];

        return $this->resolvePrompt(
            'interview_review',
            'system_prompt',
            $this->defaultSystemPrompt(),
            $placeholders,
        );
    }

    private function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a strict senior technical interviewer.

Task:
- Evaluate each candidate answer for the related interview question.
- Provide one concise and practical AI comment per question.
- Provide an overall interview summary.

Scoring rules (answer_score):
- Score each answer from 1 to 10.
- Use up to 2 decimal places.
- Keep scores realistic and grounded in the candidate answer only.
- If answer is empty or irrelevant, score it close to 1 and explain why.
- Do not skip any question.
- Some questions may include follow-up exchanges. Consider both the original answer AND the follow-up answers when scoring. A strong follow-up answer can improve the overall score.

Adequacy scoring rules (adequacy_score):
- Rate the behavioral appropriateness of each answer from 1 to 10.
- Use up to 2 decimal places.
- This is NOT about technical correctness or relevance (that is answer_score).
- Evaluate ONLY: profanity, obscene language, insults, aggression, hostility, spam, provocations, or other inappropriate behavior in an interview context.
- 10 = fully appropriate and professional conduct.
- 1 = severe violations (profanity, aggression, insults).
- Most normal answers should score 9-10 even if technically wrong.
- Do not skip any question.

Language rules:
- Write all natural-language fields strictly in {{output_language}}.
- Specifically, "summary" and each "ai_comment" must be in {{output_language}}.
- Even if candidate answers are in another language, still return text in {{output_language}}.

Output rules:
- Return only valid JSON matching the required schema.
- Do not include markdown, code fences, or extra fields.
PROMPT;
    }

    private function buildUserPrompt(Interview $interview): string
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
            'output_language' => $this->resolveOutputLanguage(),
            'payload_json' => $encodedPayload,
        ];

        return $this->resolvePrompt(
            'interview_review',
            'user_prompt',
            $this->defaultUserPrompt(),
            $placeholders,
        );
    }

    private function defaultUserPrompt(): string
    {
        return <<<'PROMPT'
Evaluate the interview data and return a structured review.
All textual fields in your JSON response must be in {{output_language}}.

Interview payload:
{{payload_json}}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonSchema(Interview $interview): array
    {
        $questionCount = $interview->interviewQuestions->whereNull('parent_question_id')->count();

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
                    'items' => [
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
                    ],
                ],
            ],
        ];
    }

    private function resolveOutputLanguage(): string
    {
        $outputLanguage = config('ai.features.interview_review.output_language');

        if (! is_string($outputLanguage) || $outputLanguage === '') {
            return 'Russian';
        }

        return match (strtolower($outputLanguage)) {
            'ru', 'russian', 'русский' => 'Russian',
            default => $outputLanguage,
        };
    }
}
