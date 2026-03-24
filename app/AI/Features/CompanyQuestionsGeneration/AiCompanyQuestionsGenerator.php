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
            $this->defaultSystemPrompt(),
            $placeholders,
        );
    }

    private function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an HR assistant creating FAQ-style company questions and concise answers for job candidates.

Task:
- Analyze provided company or vacancy description.
- Decide yourself how many question-answer pairs are useful for this description.
- Include only questions a candidate would realistically ask before or after an interview.
- Keep answers concise, factual, and candidate-friendly.

Question quality:
- Questions must be specific and practical.
- Avoid duplicates and vague formulations.
- Prefer compensation policy, process, growth, team, format, tools, and expectations when relevant.

Answer quality:
- Each answer should be direct and clear.
- Keep each answer to 1-3 short sentences.
- Write answers as if the company is speaking to the candidate in first person plural ("мы", "у нас", "предоставляем", "работаем").
- Do not reference the source text or analysis process.
- Do not use phrases like "в описании", "указано", "упоминается", "судя по", "из текста".
- Do not invent confidential or unverifiable details. If detail is not in the description, provide a safe generic answer.

Language:
{{output_language}}

Output rules:
- Return only valid JSON matching the provided schema.
- Do not include markdown, comments, or extra keys.
PROMPT;
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
            $this->defaultUserPrompt(),
            ['payload_json' => $encodedPayload],
        );
    }

    private function defaultUserPrompt(): string
    {
        return <<<'PROMPT'
Generate company FAQ style questions and answers from this input:
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
        $outputLanguage = config('ai.features.company_questions_generation.output_language');

        if (! is_string($outputLanguage) || $outputLanguage === '') {
            return 'Write all generated question and answer text in Russian.';
        }

        return match (strtolower(trim($outputLanguage))) {
            'ru', 'russian', 'русский', 'same_as_input' => 'Write all generated question and answer text in Russian.',
            default => sprintf(
                'Write all generated question and answer text in %s.',
                $outputLanguage,
            ),
        };
    }
}
