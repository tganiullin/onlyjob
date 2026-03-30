<?php

namespace App\AI\Features\CompanyQuestionsGeneration;

use App\AI\AiProviderResolver;
use App\AI\Data\AiRequest;
use App\AI\Features\CompanyQuestionsGeneration\Contracts\CompanyQuestionsGenerator;
use App\AI\Features\Concerns\ResolvesAiFeatureConfig;
use App\AI\Features\Concerns\ResolvesPrompt;
use InvalidArgumentException;

final class AiCompanyQuestionsGenerator implements CompanyQuestionsGenerator
{
    use ResolvesAiFeatureConfig, ResolvesPrompt;

    public function __construct(
        public AiProviderResolver $providerResolver,
    ) {}

    public function generate(array $context): array
    {
        $description = $this->resolveDescription($context);

        $response = $this->providerResolver
            ->resolveForFeature('company_questions_generation')
            ->generateStructured(new AiRequest(
                systemPrompt: $this->buildSystemPrompt(),
                userPrompt: $this->buildUserPrompt($description, $context['title'] ?? null),
                jsonSchema: $this->buildJsonSchema(),
                schemaName: 'company_questions',
                model: $this->resolveFeatureModel('company_questions_generation'),
                temperature: $this->resolveFeatureTemperature('company_questions_generation'),
                maxTokens: $this->resolveFeatureMaxTokens('company_questions_generation'),
            ));

        return $this->normalizeQuestions($response->content);
    }

    private function resolveDescription(array $context): string
    {
        $description = $context['description'] ?? null;

        if (! is_string($description) || trim($description) === '') {
            throw new InvalidArgumentException('Company or vacancy description is required for company questions generation.');
        }

        return trim($description);
    }

    private function buildSystemPrompt(): string
    {
        $placeholders = [
            'output_language' => $this->resolveOutputLanguageRule(),
        ];

        return $this->resolvePrompt(
            'company_questions_generation',
            'system_prompt',
            $placeholders,
        );
    }

    private function buildUserPrompt(string $description, mixed $title): string
    {
        $payload = [
            'title' => is_string($title) ? trim($title) : null,
            'description' => $description,
        ];

        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->resolvePrompt(
            'company_questions_generation',
            'user_prompt',
            ['payload_json' => $encodedPayload],
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
            'required' => ['questions'],
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 12,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['question', 'answer'],
                        'properties' => [
                            'question' => [
                                'type' => 'string',
                                'minLength' => 1,
                            ],
                            'answer' => [
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
     * @return list<array{question: string, answer: string}>
     */
    private function normalizeQuestions(array $payload): array
    {
        $questions = $payload['questions'] ?? null;

        if (! is_array($questions)) {
            throw new InvalidArgumentException('Generated company questions payload must contain a questions array.');
        }

        $normalizedQuestions = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                throw new InvalidArgumentException('Each generated company question must be an object.');
            }

            $questionText = $question['question'] ?? null;
            $answerText = $question['answer'] ?? null;

            if (! is_string($questionText) || trim($questionText) === '') {
                throw new InvalidArgumentException('Generated company question text is required.');
            }

            if (! is_string($answerText) || trim($answerText) === '') {
                throw new InvalidArgumentException('Generated company question answer is required.');
            }

            $normalizedQuestions[] = [
                'question' => trim($questionText),
                'answer' => trim($answerText),
            ];
        }

        if (count($normalizedQuestions) < 1) {
            throw new InvalidArgumentException('At least one company question-answer pair must be generated.');
        }

        return $normalizedQuestions;
    }

    private function resolveOutputLanguageRule(): string
    {
        return $this->resolvePrompt('company_questions_generation', 'output_language_template', [
            'language' => $this->resolveOutputLanguage('company_questions_generation'),
        ]);
    }
}
