<?php

namespace App\AI\Features\QuestionGeneration;

use App\AI\AiProviderResolver;
use App\AI\Data\AiRequest;
use App\AI\Features\Concerns\ResolvesAiFeatureConfig;
use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\Enums\PositionLevel;
use InvalidArgumentException;

final class AiQuestionGenerator implements QuestionGenerator
{
    use ResolvesAiFeatureConfig;

    public function __construct(
        public AiProviderResolver $providerResolver,
    ) {}

    public function generate(array $context): array
    {
        $description = $this->resolveDescription($context);
        $positionLevel = $this->resolvePositionLevel($context);
        $questionsCount = $this->resolveQuestionsCount($context);
        $focus = $this->resolveFocus($context);

        $response = $this->providerResolver
            ->resolveForFeature('question_generation')
            ->generateStructured(new AiRequest(
                systemPrompt: $this->buildSystemPrompt($positionLevel, $focus),
                userPrompt: $this->buildUserPrompt(
                    description: $description,
                    positionLevel: $positionLevel,
                    questionsCount: $questionsCount,
                    focus: $focus,
                ),
                jsonSchema: $this->buildJsonSchema($questionsCount),
                schemaName: 'position_questions',
                model: $this->resolveFeatureModel('question_generation'),
                temperature: $this->resolveFeatureTemperature('question_generation'),
                maxTokens: $this->resolveFeatureMaxTokens('question_generation'),
            ));

        return $this->normalizeQuestions($response->content, $questionsCount);
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

    private function buildSystemPrompt(PositionLevel $positionLevel, string $focus): string
    {
        $focusGuideline = $this->resolveFocusGuideline($focus);
        $levelGuideline = $this->resolveLevelGuideline($positionLevel);
        $outputLanguage = $this->resolveOutputLanguageRule();

        return <<<PROMPT
You are a senior technical interviewer creating structured screening interview questions.

Task:
- Generate practical interview questions from the position description.
- Adjust depth and complexity to the target level: {$positionLevel->getLabel()}.
- Keep each question concise and specific.
- Provide one short evaluation instruction for each question.

Level alignment:
{$levelGuideline}

Focus:
{$focusGuideline}

Language:
{$outputLanguage}

Output rules:
- Return only valid JSON matching the provided schema.
- Do not include markdown, comments, or extra keys.
PROMPT;
    }

    private function buildUserPrompt(
        string $description,
        PositionLevel $positionLevel,
        int $questionsCount,
        string $focus,
    ): string {
        $payload = [
            'description' => $description,
            'level' => $positionLevel->value,
            'level_label' => $positionLevel->getLabel(),
            'questions_count' => $questionsCount,
            'focus' => $focus,
        ];

        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return <<<PROMPT
Generate {$questionsCount} interview questions based on this input:
{$encodedPayload}
PROMPT;
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
        return match ($focus) {
            'soft_skills' => 'Prioritize communication, ownership, teamwork, and stakeholder collaboration questions with role context.',
            'mixed' => 'Balance technical depth with collaboration and communication competency checks.',
            default => 'Prioritize technical hard-skill questions: architecture, debugging, implementation, and trade-offs.',
        };
    }

    private function resolveLevelGuideline(PositionLevel $positionLevel): string
    {
        return match ($positionLevel) {
            PositionLevel::Junior => '- Focus on fundamentals, basic troubleshooting, and clear understanding of core concepts.',
            PositionLevel::Middle => '- Focus on practical implementation, debugging, maintainability, and reasonable trade-offs.',
            PositionLevel::Senior => '- Focus on architecture decisions, scalability, reliability, and nuanced trade-offs.',
            PositionLevel::Lead => '- Focus on system strategy, cross-team impact, technical leadership, and decision quality.',
        };
    }

    private function resolveOutputLanguageRule(): string
    {
        $outputLanguage = config('ai.features.question_generation.output_language');

        if (! is_string($outputLanguage) || $outputLanguage === '') {
            return 'Write all generated question text and evaluation instructions in Russian.';
        }

        return match (strtolower(trim($outputLanguage))) {
            'ru', 'russian', 'русский', 'same_as_input' => 'Write all generated question text and evaluation instructions in Russian.',
            default => sprintf(
                'Write all generated question text and evaluation instructions in %s.',
                $outputLanguage,
            ),
        };
    }
}
