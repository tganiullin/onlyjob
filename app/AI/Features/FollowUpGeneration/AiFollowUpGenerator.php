<?php

namespace App\AI\Features\FollowUpGeneration;

use App\AI\AiProviderResolver;
use App\AI\Data\AiRequest;
use App\AI\Features\Concerns\ResolvesAiFeatureConfig;
use App\AI\Features\Concerns\ResolvesPrompt;
use App\AI\Features\FollowUpGeneration\Contracts\FollowUpGenerator;
use App\AI\Features\FollowUpGeneration\Data\FollowUpResult;
use App\Models\InterviewQuestion;

final class AiFollowUpGenerator implements FollowUpGenerator
{
    use ResolvesAiFeatureConfig, ResolvesPrompt;

    public function __construct(
        public AiProviderResolver $providerResolver,
    ) {}

    public function generate(InterviewQuestion $question, ?int $minScore = null): FollowUpResult
    {
        $question->loadMissing(['interview.position', 'followUps']);

        $response = $this->providerResolver
            ->resolveForFeature('follow_up_generation')
            ->generateStructured(new AiRequest(
                systemPrompt: $this->buildSystemPrompt($minScore),
                userPrompt: $this->buildUserPrompt($question),
                jsonSchema: $this->buildJsonSchema(),
                schemaName: 'follow_up_result',
                model: $this->resolveFeatureModel('follow_up_generation'),
                temperature: $this->resolveFeatureTemperature('follow_up_generation'),
                maxTokens: $this->resolveFeatureMaxTokens('follow_up_generation'),
            ));

        return FollowUpResult::fromArray($response->content);
    }

    private function buildSystemPrompt(?int $minScore): string
    {
        $placeholders = [
            'output_language' => $this->resolveOutputLanguage(),
            'min_score' => $minScore !== null ? (string) $minScore : 'not set',
            'min_score_instruction' => $minScore !== null
                ? "The minimum expected answer quality is {$minScore}/10. If the answer is clearly below this threshold, a follow-up is needed."
                : 'Use your expert judgment to decide if the answer quality warrants a follow-up question.',
        ];

        return $this->resolvePrompt(
            'follow_up_generation',
            'system_prompt',
            $this->defaultSystemPrompt(),
            $placeholders,
        );
    }

    private function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a senior technical interviewer deciding whether a candidate's answer needs clarification.

Task:
- Analyze the candidate's answer to the interview question.
- Decide if a follow-up question is needed.
- If needed, generate ONE concise follow-up question.

When to generate a follow-up:
- The answer is vague, incomplete, or only partially addresses the question.
- The candidate clearly misunderstood the question.
- The candidate is ASKING FOR CLARIFICATION (e.g. "уточните", "не понял вопрос", "что конкретно имеется в виду", "можно переформулировать?"). This is a MANDATORY follow-up — rephrase the original question in simpler, more concrete terms.
- The answer lacks important details or concrete examples that were expected.
- {{min_score_instruction}}

When NOT to generate a follow-up:
- The answer is empty, blank, or explicitly skipped (e.g. "Не знаю ответ") — never follow up on skipped answers.
- The answer already covers the topic adequately, even if imperfect.
- The answer shows clear understanding regardless of minor inaccuracies.

Follow-up question rules:
- The follow-up must directly relate to the original question.
- If the candidate asked for clarification: rephrase the original question in simpler terms, add a concrete example or narrow the scope to help the candidate understand what is expected.
- If the answer was incomplete: ask about the specific missing detail.
- Do not repeat the original question word-for-word. Rephrase or narrow the scope.
- Do not ask a completely new or unrelated question.
- Keep it concise (1-2 sentences).

Language rules:
- Write the follow-up question in {{output_language}}.

Output rules:
- Return only valid JSON matching the required schema.
- Do not include markdown, code fences, or extra fields.
PROMPT;
    }

    private function buildUserPrompt(InterviewQuestion $question): string
    {
        $existingFollowUps = $question->followUps
            ->map(static fn (InterviewQuestion $followUp): array => [
                'follow_up_question' => $followUp->question_text,
                'candidate_answer' => $followUp->candidate_answer,
            ])
            ->all();

        $payload = [
            'question' => $question->question_text,
            'evaluation_instructions' => $question->evaluation_instructions_snapshot,
            'candidate_answer' => $question->candidate_answer,
            'existing_follow_ups' => $existingFollowUps,
        ];

        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $placeholders = [
            'output_language' => $this->resolveOutputLanguage(),
            'payload_json' => $encodedPayload,
        ];

        return $this->resolvePrompt(
            'follow_up_generation',
            'user_prompt',
            $this->defaultUserPrompt(),
            $placeholders,
        );
    }

    private function defaultUserPrompt(): string
    {
        return <<<'PROMPT'
Analyze the candidate's answer and decide if a follow-up question is needed.
If a follow-up is needed, write the follow-up question in {{output_language}}.

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
            'required' => ['needs_follow_up', 'follow_up_question'],
            'properties' => [
                'needs_follow_up' => [
                    'type' => 'boolean',
                ],
                'follow_up_question' => [
                    'type' => ['string', 'null'],
                ],
            ],
        ];
    }

    private function resolveOutputLanguage(): string
    {
        $outputLanguage = config('ai.features.follow_up_generation.output_language');

        if (! is_string($outputLanguage) || $outputLanguage === '') {
            return 'Russian';
        }

        return match (strtolower($outputLanguage)) {
            'ru', 'russian', 'русский' => 'Russian',
            default => $outputLanguage,
        };
    }
}
