<?php

namespace App\AI\Features\QuestionGeneration;

use App\AI\AiProviderResolver;
use App\AI\Data\AiRequest;
use App\AI\Features\Concerns\ResolvesAiFeatureConfig;
use App\AI\Features\Concerns\ResolvesPrompt;
use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use BackedEnum;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class AiQuestionGenerator implements QuestionGenerator
{
    use ResolvesAiFeatureConfig, ResolvesPrompt;

    public function __construct(
        public AiProviderResolver $providerResolver,
    ) {}

    public function generate(array $context): array
    {
        $description = $this->resolveDescription($context);
        $positionLevel = $this->resolvePositionLevel($context);
        $questionsCount = $this->resolveQuestionsCount($context);
        $focus = $this->resolveFocus($context);
        $answerTime = $this->resolveAnswerTime($context);

        $systemPrompt = $this->buildSystemPrompt($positionLevel, $focus, $answerTime);
        $userPrompt = $this->buildUserPrompt(
            description: $description,
            positionLevel: $positionLevel,
            questionsCount: $questionsCount,
            focus: $focus,
            answerTime: $answerTime,
        );

        Log::info('AiQuestionGenerator: sending request', [
            'model' => $this->resolveFeatureModel('question_generation'),
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
        ]);

        $response = $this->providerResolver
            ->resolveForFeature('question_generation')
            ->generateStructured(new AiRequest(
                systemPrompt: $systemPrompt,
                userPrompt: $userPrompt,
                jsonSchema: $this->buildJsonSchema($questionsCount),
                schemaName: 'position_questions',
                model: $this->resolveFeatureModel('question_generation'),
                temperature: $this->resolveFeatureTemperature('question_generation'),
                maxTokens: $this->resolveFeatureMaxTokens('question_generation'),
            ));

        $questions = $this->normalizeQuestions($response->content, $questionsCount);

        return $questions;
    }

    private function resolveDescription(array $context): string
    {
        $description = $context['description'] ?? null;

        if (! is_string($description) || trim($description) === '') {
            throw new InvalidArgumentException('Position description is required for question generation.');
        }

        return trim($description);
    }

    private function resolvePositionLevel(array $context): PositionLevel
    {
        $level = $context['level'] ?? null;

        if (! is_string($level)) {
            throw new InvalidArgumentException('Position level is required for question generation.');
        }

        $positionLevel = PositionLevel::tryFrom(strtolower(trim($level)));

        if (! $positionLevel instanceof PositionLevel) {
            throw new InvalidArgumentException('Position level is invalid for question generation.');
        }

        return $positionLevel;
    }

    private function resolveQuestionsCount(array $context): int
    {
        $questionsCount = $context['questions_count'] ?? null;

        if (! is_numeric($questionsCount)) {
            return 5;
        }

        return max(1, min(15, (int) $questionsCount));
    }

    private function resolveFocus(array $context): string
    {
        $focus = $context['focus'] ?? null;

        if (! is_string($focus) || trim($focus) === '') {
            return 'hard_skills';
        }

        return match ($focus) {
            'hard_skills', 'mixed', 'soft_skills' => $focus,
            default => 'hard_skills',
        };
    }

    private function resolveAnswerTime(array $context): PositionAnswerTime
    {
        $answerTime = $context['answer_time_seconds'] ?? null;

        if ($answerTime instanceof PositionAnswerTime) {
            return $answerTime;
        }

        if ($answerTime instanceof BackedEnum) {
            $answerTime = $answerTime->value;
        }

        if (is_string($answerTime)) {
            $answerTime = trim($answerTime);
        }

        if (is_int($answerTime) || is_numeric($answerTime)) {
            $resolved = PositionAnswerTime::tryFrom((int) $answerTime);

            if ($resolved instanceof PositionAnswerTime) {
                return $resolved;
            }
        }

        return PositionAnswerTime::TwoMinutesThirtySeconds;
    }

    private function buildSystemPrompt(PositionLevel $positionLevel, string $focus, PositionAnswerTime $answerTime): string
    {
        $placeholders = [
            'level_label' => $positionLevel->getLabel(),
            'level_guideline' => $this->resolveLevelGuideline($positionLevel),
            'focus_guideline' => $this->resolveFocusGuideline($focus),
            'answer_time_guideline' => $this->resolveAnswerTimeGuideline($answerTime),
            'output_language' => $this->resolveOutputLanguageRule(),
        ];

        return $this->resolvePrompt(
            'question_generation',
            'system_prompt',
            $placeholders,
        );
    }

    private function buildUserPrompt(
        string $description,
        PositionLevel $positionLevel,
        int $questionsCount,
        string $focus,
        PositionAnswerTime $answerTime,
    ): string {
        $payload = [
            'description' => $description,
            'level' => $positionLevel->value,
            'level_label' => $positionLevel->getLabel(),
            'questions_count' => $questionsCount,
            'focus' => $focus,
            'answer_time_seconds' => $answerTime->value,
            'answer_time_label' => $answerTime->getLabel(),
        ];

        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $placeholders = [
            'questions_count' => (string) $questionsCount,
            'payload_json' => $encodedPayload,
        ];

        return $this->resolvePrompt(
            'question_generation',
            'user_prompt',
            $placeholders,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonSchema(int $questionsCount): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['questions'],
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'minItems' => $questionsCount,
                    'maxItems' => $questionsCount,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['text', 'evaluation_instructions'],
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'minLength' => 1,
                            ],
                            'evaluation_instructions' => [
                                'type' => 'string',
                                'minLength' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, string>>
     */
    private function normalizeQuestions(array $payload, int $expectedCount): array
    {
        $questions = $payload['questions'] ?? null;

        if (! is_array($questions)) {
            throw new InvalidArgumentException('Generated questions payload must contain a questions array.');
        }

        $normalizedQuestions = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                throw new InvalidArgumentException('Each generated question must be an object.');
            }

            $text = $question['text'] ?? null;
            $evaluationInstructions = $question['evaluation_instructions'] ?? null;

            if (! is_string($text) || trim($text) === '') {
                throw new InvalidArgumentException('Generated question text is required.');
            }

            if (! is_string($evaluationInstructions) || trim($evaluationInstructions) === '') {
                throw new InvalidArgumentException('Generated evaluation instructions are required.');
            }

            $normalizedQuestions[] = [
                'text' => trim($text),
                'evaluation_instructions' => trim($evaluationInstructions),
            ];
        }

        if (count($normalizedQuestions) !== $expectedCount) {
            throw new InvalidArgumentException(sprintf(
                'Generated questions count mismatch. Expected %d, got %d.',
                $expectedCount,
                count($normalizedQuestions),
            ));
        }

        return $normalizedQuestions;
    }

    private function resolveFocusGuideline(string $focus): string
    {
        $type = match ($focus) {
            'soft_skills', 'mixed' => "focus_guideline_{$focus}",
            default => 'focus_guideline_hard_skills',
        };

        return $this->resolvePrompt('question_generation', $type);
    }

    private function resolveLevelGuideline(PositionLevel $positionLevel): string
    {
        return $this->resolvePrompt('question_generation', "level_guideline_{$positionLevel->value}");
    }

    private function resolveAnswerTimeGuideline(PositionAnswerTime $answerTime): string
    {
        return $this->resolvePrompt('question_generation', 'answer_time_guideline', [
            'label' => $answerTime->getLabel(),
            'seconds' => (string) $answerTime->value,
        ]);
    }

    private function resolveOutputLanguageRule(): string
    {
        return $this->resolvePrompt('question_generation', 'output_language_template', [
            'language' => $this->resolveOutputLanguage('question_generation'),
        ]);
    }
}
