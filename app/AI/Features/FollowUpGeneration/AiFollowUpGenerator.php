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

        $outputLanguage = $this->resolveOutputLanguage('follow_up_generation');

        $response = $this->providerResolver
            ->resolveForFeature('follow_up_generation')
            ->generateStructured(new AiRequest(
                systemPrompt: $this->buildSystemPrompt($minScore, $outputLanguage),
                userPrompt: $this->buildUserPrompt($question, $outputLanguage),
                jsonSchema: $this->buildJsonSchema(),
                schemaName: 'follow_up_result',
                model: $this->resolveFeatureModel('follow_up_generation'),
                temperature: $this->resolveFeatureTemperature('follow_up_generation'),
                maxTokens: $this->resolveFeatureMaxTokens('follow_up_generation'),
            ));

        return FollowUpResult::fromArray($response->content);
    }

    private function buildSystemPrompt(?int $minScore, string $outputLanguage): string
    {
        $placeholders = [
            'output_language' => $outputLanguage,
            'min_score' => $minScore !== null ? (string) $minScore : 'not set',
            'min_score_instruction' => $this->resolveMinScoreInstruction($minScore),
        ];

        return $this->resolvePrompt(
            'follow_up_generation',
            'system_prompt',
            $placeholders,
        );
    }

    private function buildUserPrompt(InterviewQuestion $question, string $outputLanguage): string
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
            'output_language' => $outputLanguage,
            'payload_json' => $encodedPayload,
        ];

        return $this->resolvePrompt(
            'follow_up_generation',
            'user_prompt',
            $placeholders,
        );
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

    private function resolveMinScoreInstruction(?int $minScore): string
    {
        if ($minScore === null) {
            return $this->resolvePrompt('follow_up_generation', 'min_score_instruction_default');
        }

        return $this->resolvePrompt('follow_up_generation', 'min_score_instruction', [
            'score' => (string) $minScore,
        ]);
    }
}
