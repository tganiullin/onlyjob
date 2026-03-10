# OnlyJob AI Interviewer (MVP)

Сервис для проведения и проверки интервью с помощью AI.

Текущий MVP покрывает:
- управление позициями и вопросами в админке;
- фиксацию (snapshot) вопросов в момент старта интервью;
- ручной запуск AI-проверки завершенного интервью;
- автоматический расчет итоговой оценки и статуса passed/failed.

## Технологии

- PHP 8.x
- Laravel 12
- Filament 5 (админка)
- MySQL
- Laravel Sail
- openai-php/laravel
- PHPUnit

## Основные сущности

### `positions`

- `title`
- `minimum_score` (порог прохождения 1..10)
- `answer_time_seconds` (enum значений времени)
- `level` (`junior`, `middle`, `senior`, `lead`)
- soft delete (архивация вместо удаления)

### `questions`

- принадлежат `position` (`position_id`)
- `text`
- `evaluation_instructions` (инструкции для оценки конкретного вопроса)
- `sort_order` (drag-and-drop в админке)

Важно: уникальность `(position_id, sort_order)` убрана для корректного переставления вопросов.

### `interviews`

- `position_id` (nullable, `withTrashed` в модели)
- данные кандидата: `first_name`, `last_name`, `email`, `phone`
- `status`: `pending`, `completed`, `passed`, `failed`
- `score` (среднее по ответам 1..10, decimal)
- `candidate_feedback_rating` (1..5)
- `summary`
- `started_at`, `completed_at`

### `interview_questions`

Snapshot вопросов на момент создания интервью:
- ссылка на оригинальный `question_id` (nullable)
- `question_text`
- `evaluation_instructions_snapshot`
- `candidate_answer`
- `ai_comment`
- `answer_score` (1..10)
- `sort_order`

## Бизнес-логика интервью

1. При создании `Interview` автоматически создаются `interview_questions` из текущих вопросов позиции.
2. Snapshot независим от последующих изменений в `questions`.
3. `interviews.score` автоматически пересчитывается как среднее `interview_questions.answer_score`.
4. AI-проверка запускается только для интервью со статусом `completed`.
5. После AI-проверки:
   - сохраняются `ai_comment` и `answer_score` по каждому вопросу;
   - сохраняется `summary`;
   - пересчитывается `score`;
   - `status` меняется на `passed` или `failed` по `position.minimum_score`.

## AI-архитектура

Слой находится в `app/AI` и разбит на уровни:

- `Contracts/AiProvider` — общий интерфейс провайдера.
- `Providers/OpenAiProvider` — адаптер OpenAI.
- `AiProviderResolver` — выбор провайдера по feature из `config/ai.php`.
- `Data/AiRequest`, `Data/AiStructuredResponse` — DTO запроса/ответа.
- `Features/InterviewReview/*` — feature-логика AI-оценки интервью.

### Провайдер OpenAI

`OpenAiProvider` использует **Responses API** (`v1/responses`), а не `chat/completions`.

Это важно для моделей, которые не поддерживают chat endpoint (например семейство codex-моделей).

### Prompt и формат ответа

`AiInterviewReviewer` формирует:
- `system prompt` с правилами оценки;
- `user prompt` с payload интервью;
- строгую `json_schema` для структурированного ответа.

Язык ответа задается через конфиг:
- `AI_INTERVIEW_REVIEW_OUTPUT_LANGUAGE=ru` (по умолчанию)

В AI передаются:
- данные позиции (`id`, `title`, `level`, `minimum_score`);
- данные кандидата (`first_name`, `last_name`, `email`);
- данные интервью (`id`, `status`, `started_at`, `completed_at`);
- по каждому вопросу:
  - `interview_question_id`
  - `sort_order`
  - `question`
  - `evaluation_instructions` (snapshot)
  - `candidate_answer`

## Очередь и job

`CheckInterviewJob`:
- `ShouldQueue`
- `ShouldBeUnique` (уникальность по `interviewId`, `uniqueFor=600`)
- выполняет проверку только если:
  - интервью существует;
  - статус `completed`;
  - есть вопросы в snapshot.

Запуск job из админки:
- в таблице интервью есть row action `Queue AI review` (видна только для `completed`).

## Админка (Filament)

- Панель: `/admin`
- Ресурсы:
  - `Positions` (архивация/восстановление, управление вопросами)
  - `Interviews` (редактирование, запуск AI-review)

## Конфигурация

### Обязательные переменные `.env`

- `OPENAI_API_KEY`
- `AI_PROVIDER=openai`
- `AI_OPENAI_MODEL=<model_name>`

### Опциональные переменные `.env`

- `AI_INTERVIEW_REVIEW_PROVIDER` (по умолчанию наследует `AI_PROVIDER`)
- `AI_INTERVIEW_REVIEW_MODEL` (по умолчанию наследует `AI_OPENAI_MODEL`)
- `AI_INTERVIEW_REVIEW_TEMPERATURE` (default `0.1`)
- `AI_INTERVIEW_REVIEW_MAX_TOKENS` (default `2500`)
- `AI_INTERVIEW_REVIEW_OUTPUT_LANGUAGE` (default `ru`)
- `AI_OPENAI_TEMPERATURE` (default `0.1`)

После изменения env/конфига:
- `./vendor/bin/sail artisan config:clear`
- если запущен долгоживущий worker, перезапустить его.

## Локальный запуск (Sail)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
```

Запустить очередь отдельно:

```bash
./vendor/bin/sail artisan queue:work
```

Или использовать dev-скрипт проекта:

```bash
./vendor/bin/sail composer run dev
```

## Тесты

Запуск всех тестов:

```bash
./vendor/bin/sail artisan test --compact
```

Ключевые feature-тесты MVP:
- `tests/Feature/PositionTest.php`
- `tests/Feature/InterviewTest.php`
- `tests/Feature/CheckInterviewJobTest.php`

Для AI используются fake-провайдеры в тестах (`tests/Fakes/FakeAiProvider.php`), чтобы тесты не зависели от реального OpenAI API.

## Частые проблемы

### Модель не поддерживает chat/completions

Ошибка вида:
`This is not a chat model and thus not supported in the v1/chat/completions endpoint`

Решение: уже учтено в проекте — используется `responses` endpoint.

### Изменил `.env`, но модель/параметры не применились

1. `./vendor/bin/sail artisan config:clear`
2. Перезапусти queue worker.

### Фронтенд-изменения не видны

Запусти:
- `./vendor/bin/sail npm run dev`
или
- `./vendor/bin/sail npm run build`
