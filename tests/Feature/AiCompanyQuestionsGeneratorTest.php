<?php

namespace Tests\Feature;

use App\AI\Features\CompanyQuestionsGeneration\Contracts\CompanyQuestionsGenerator;
use Database\Seeders\AiPromptSeeder;
use InvalidArgumentException;
use Tests\Fakes\FakeAiProvider;
use Tests\TestCase;

class AiCompanyQuestionsGeneratorTest extends TestCase
{
    public function test_it_generates_company_questions_and_allows_ai_to_choose_count(): void
    {
        $this->seed(AiPromptSeeder::class);

        $provider = new FakeAiProvider([
            [
                'questions' => [
                    [
                        'question' => 'Какой формат работы по этой вакансии?',
                        'answer' => 'Команда работает в гибридном формате: часть дней удаленно, часть в офисе.',
                    ],
                    [
                        'question' => 'Как устроен процесс адаптации в первые недели?',
                        'answer' => 'У вас будет онбординг-план, ментор и регулярные встречи с тимлидом.',
                    ],
                    [
                        'question' => 'Как часто проводится performance review?',
                        'answer' => 'Обычно ревью проводится раз в полгода с фиксацией целей и прогресса.',
                    ],
                    [
                        'question' => 'Есть ли бюджет на обучение?',
                        'answer' => 'Да, компания выделяет бюджет на курсы, конференции и профильные сертификаты.',
                    ],
                ],
            ],
        ]);
        $this->useFakeAiProvider($provider);

        $questions = app(CompanyQuestionsGenerator::class)->generate([
            'title' => 'Backend Engineer',
            'description' => 'Продуктовая IT-компания с гибридным форматом работы, регулярными review и бюджетом на обучение.',
        ]);

        $this->assertCount(4, $questions);
        $this->assertSame('Какой формат работы по этой вакансии?', $questions[0]['question']);
        $this->assertSame(1, $provider->callCount);
        $this->assertStringContainsString('Decide yourself how many question-answer pairs are useful', $provider->requests[0]->systemPrompt);
        $this->assertStringContainsString('"title": "Backend Engineer"', $provider->requests[0]->userPrompt);
        $this->assertSame(3, data_get($provider->requests[0]->jsonSchema, 'properties.questions.minItems'));
        $this->assertSame(12, data_get($provider->requests[0]->jsonSchema, 'properties.questions.maxItems'));
    }

    public function test_it_throws_exception_when_company_questions_payload_is_invalid(): void
    {
        $this->seed(AiPromptSeeder::class);

        $provider = new FakeAiProvider([
            [
                'foo' => 'bar',
            ],
        ]);
        $this->useFakeAiProvider($provider);

        $this->expectException(InvalidArgumentException::class);

        app(CompanyQuestionsGenerator::class)->generate([
            'description' => 'Описание компании.',
        ]);
    }

    private function useFakeAiProvider(FakeAiProvider $provider): void
    {
        config()->set('ai.default_provider', 'fake');
        config()->set('ai.providers.fake', FakeAiProvider::class);
        config()->set('ai.features.company_questions_generation.provider', 'fake');

        app()->instance(FakeAiProvider::class, $provider);
    }
}
