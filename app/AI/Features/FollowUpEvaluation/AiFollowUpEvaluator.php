<?php

namespace App\AI\Features\FollowUpEvaluation;

use App\AI\AiProviderResolver;
use App\AI\Data\AiRequest;
use App\AI\Features\Concerns\ResolvesAiFeatureConfig;
use App\AI\Features\Concerns\ResolvesPrompt;
use App\AI\Features\FollowUpEvaluation\Contracts\FollowUpEvaluator;
use App\AI\Features\FollowUpEvaluation\Data\FollowUpEvaluationResult;
use App\Models\InterviewQuestion;
use App\Models\Position;

final class AiFollowUpEvaluator implements FollowUpEvaluator
{
    use ResolvesAiFeatureConfig, ResolvesPrompt;

    private const FEATURE_KEY = 'follow_up_evaluation';

    public function __construct(
        private AiProviderResolver $providerResolver,
    ) {}

    public function evaluate(InterviewQuestion $interviewQuestion, Position $position): FollowUpEvaluationResult
    {
        $response = $this->providerResolver
            ->resolveForFeature(self::FEATURE_KEY)
            ->generateStructured(new AiRequest(
                systemPrompt: $this->buildSystemPrompt(),
                userPrompt: $this->buildUserPrompt($interviewQuestion, $position),
                jsonSchema: $this->buildJsonSchema(),
                schemaName: 'follow_up_evaluation_result',
                model: $this->resolveFeatureModel(self::FEATURE_KEY),
                temperature: $this->resolveFeatureTemperature(self::FEATURE_KEY),
                maxTokens: $this->resolveFeatureMaxTokens(self::FEATURE_KEY),
            ));

        return FollowUpEvaluationResult::fromArray($response->content);
    }

    private function buildSystemPrompt(): string
    {
        return $this->resolvePrompt(
            self::FEATURE_KEY,
            'system_prompt',
            $this->defaultSystemPrompt(),
            ['output_language' => $this->resolveOutputLanguage()],
        );
    }

    private function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a strict senior technical interviewer conducting a live interview.

Task:
- Evaluate the candidate's answer to the given interview question.
- Determine whether the answer is weak, incomplete, or too vague and needs a follow-up.
- If a follow-up is needed, generate a single targeted question that probes deeper into the topic the candidate failed to cover or explained poorly.

Evaluation rules:
- Score the answer from 1 to 10 (up to 2 decimal places).
- If the answer is empty, irrelevant, or "I don't know", score close to 1 and do NOT generate a follow-up.
- If the score is below the threshold provided in the payload, set needs_follow_up to true.
- If the score is at or above the threshold, set needs_follow_up to false.

Follow-up question rules:
- The follow-up must directly relate to the original question and the gaps in the candidate's answer.
- Keep it concise and natural — as if you're a real interviewer asking a clarifying question.
- Do not repeat the original question verbatim.
- Do not ask something completely unrelated.

Language rules:
- Write the follow_up_question strictly in {{output_language}}.
- Even if the candidate answered in another language, return the follow-up in {{output_language}}.

Output rules:
- Return only valid JSON matching the required schema.
- Do not include markdown, code fences, or extra fields.
PROMPT;
    }

    private function buildUserPrompt(InterviewQuestion $interviewQuestion, Position $position): string
    {
        $payload = [
            'position_title' => $position->title,
            'position_level' => $position->level?->value,
            'score_threshold' => (float) $position->follow_up_score_threshold,
            'question_text' => $interviewQuestion->question_text,
            'evaluation_instructions' => $interviewQuestion->evaluation_instructions_snapshot,
            'candidate_answer' => $interviewQuestion->candidate_answer,
        ];

        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->resolvePrompt(
            self::FEATURE_KEY,
            'user_prompt',
            $this->defaultUserPrompt(),
            [
                'output_language' => $this->resolveOutputLanguage(),
                'payload_json' => $encodedPayload,
            ],
        );
    }

    private function defaultUserPrompt(): string
    {
        return <<<'PROMPT'
Evaluate the following interview answer and decide if a follow-up question is needed.
All textual fields in your JSON response must be in {{output_language}}.

Interview data:
{{payload_json}}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['needs_follow_up', 'score_estimate', 'follow_up_question'],
            'properties' => [
                'needs_follow_up' => [
                    'type' => 'boolean',
                ],
                'score_estimate' => [
                    'type' => 'number',
                    'minimum' => 1,
                    'maximum' => 10,
                ],
                'follow_up_question' => [
                    'type' => ['string', 'null'],
                ],
            ],
        ];
    }

    private function resolveOutputLanguage(): string
    {
        $outputLanguage = config('ai.features.follow_up_evaluation.output_language');

        if (! is_string($outputLanguage) || $outputLanguage === '') {
            return 'Russian';
        }

        return match (strtolower($outputLanguage)) {
            'ru', 'russian', 'русский' => 'Russian',
            default => $outputLanguage,
        };
    }
}
