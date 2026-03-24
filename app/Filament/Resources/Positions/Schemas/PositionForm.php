<?php

namespace App\Filament\Resources\Positions\Schemas;

use App\AI\Features\CompanyQuestionsGeneration\Contracts\CompanyQuestionsGenerator;
use App\AI\Features\QuestionGeneration\Contracts\QuestionGenerator;
use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use App\Models\Position;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Throwable;

class PositionForm
{
    /**
     * @var list<string>
     */
    private const LEVEL_STATE_PATHS = ['level', '../level', '../../level', '../../../level'];

    /**
     * @var list<string>
     */
    private const ANSWER_TIME_STATE_PATHS = ['answer_time_seconds', '../answer_time_seconds', '../../answer_time_seconds', '../../../answer_time_seconds'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Position details')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Requirements')
                    ->columnSpanFull()
                    ->schema([
                        ToggleButtons::make('minimum_score')
                            ->label('Minimum score')
                            ->options(array_combine(range(1, 10), range(1, 10)))
                            ->inline()
                            ->default(5)
                            ->required(),
                        ToggleButtons::make('answer_time_seconds')
                            ->label('Time per answer')
                            ->options(PositionAnswerTime::class)
                            ->inline()
                            ->default(PositionAnswerTime::TwoMinutesThirtySeconds->value)
                            ->required(),
                        ToggleButtons::make('level')
                            ->label('Level')
                            ->options(PositionLevel::class)
                            ->inline()
                            ->default(PositionLevel::Middle->value)
                            ->required(),
                    ]),
                Section::make('Follow-up questions')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('follow_up_enabled')
                            ->label('Enable AI follow-up questions')
                            ->helperText('When enabled, AI will generate follow-up questions for weak or incomplete answers during the interview.')
                            ->live(),
                        ToggleButtons::make('follow_up_score_threshold')
                            ->label('Score threshold')
                            ->helperText('Answers estimated below this score will trigger a follow-up question.')
                            ->options([
                                '2.00' => '2',
                                '3.00' => '3',
                                '4.00' => '4',
                                '5.00' => '5',
                                '6.00' => '6',
                                '7.00' => '7',
                            ])
                            ->inline()
                            ->default('4.00')
                            ->visible(static fn (Get $get): bool => (bool) $get('follow_up_enabled')),
                        ToggleButtons::make('max_follow_ups_per_question')
                            ->label('Max follow-ups per question')
                            ->options([
                                1 => '1',
                                2 => '2',
                                3 => '3',
                            ])
                            ->inline()
                            ->default(1)
                            ->visible(static fn (Get $get): bool => (bool) $get('follow_up_enabled')),
                    ]),
                Section::make('Public access')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_public')
                            ->label('Public link')
                            ->helperText('Enable to allow candidates to start interview from a public URL.'),
                        TextInput::make('public_link')
                            ->label('Public link URL')
                            ->readOnly()
                            ->dehydrated(false)
                            ->copyable(copyMessage: 'Public link copied', copyMessageDuration: 1500)
                            ->helperText('The link appears after the position is saved as public.')
                            ->formatStateUsing(static fn (?Position $record): ?string => $record?->public_url)
                            ->visible(static fn (?Position $record): bool => $record instanceof Position && filled($record->public_url)),
                    ]),
                Tabs::make('question_sections')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Interview questions')
                            ->schema([
                                Section::make('AI assistant')
                                    ->schema([
                                        Textarea::make('ai_description')
                                            ->label('Position description')
                                            ->rows(8)
                                            ->dehydrated(false)
                                            ->helperText('Describe responsibilities, stack, and expected outcomes.')
                                            ->belowContent(
                                                Action::make('generateQuestions')
                                                    ->label('Generate')
                                                    ->color('primary')
                                                    ->action(static function (Get $get, Set $set): void {
                                                        self::handleGenerateQuestions($get, $set);
                                                    }),
                                            ),
                                        TextInput::make('ai_questions_count')
                                            ->label('How many questions to generate?')
                                            ->numeric()
                                            ->default(5)
                                            ->minValue(1)
                                            ->maxValue(15)
                                            ->dehydrated(false),
                                        Select::make('ai_focus')
                                            ->label('What should we evaluate?')
                                            ->options([
                                                'hard_skills' => 'Primarily hard skills',
                                                'mixed' => 'Balanced hard + soft skills',
                                                'soft_skills' => 'Primarily soft skills',
                                            ])
                                            ->default('hard_skills')
                                            ->native(false)
                                            ->dehydrated(false),
                                    ]),
                                Repeater::make('questions')
                                    ->relationship()
                                    ->label('Questions')
                                    ->defaultItems(0)
                                    ->columnSpanFull()
                                    ->schema([
                                        Textarea::make('text')
                                            ->label('Question')
                                            ->required()
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        Textarea::make('evaluation_instructions')
                                            ->label('Evaluation instructions')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->orderColumn('sort_order')
                                    ->addActionLabel('Add question')
                                    ->itemLabel(static fn (array $state): ?string => filled($state['text'] ?? null) ? (string) $state['text'] : 'New question')
                                    ->collapsible()
                                    ->collapsed(),
                            ]),
                        Tab::make('Company questions')
                            ->schema([
                                Section::make('AI assistant')
                                    ->schema([
                                        Textarea::make('ai_company_description')
                                            ->label('Company or vacancy description')
                                            ->rows(8)
                                            ->dehydrated(false)
                                            ->helperText('Describe the company or role details candidates usually ask about.')
                                            ->belowContent(
                                                Action::make('generateCompanyQuestions')
                                                    ->label('Generate company questions')
                                                    ->color('primary')
                                                    ->action(static function (Get $get, Set $set): void {
                                                        self::handleGenerateCompanyQuestions($get, $set);
                                                    }),
                                            ),
                                    ]),
                                Repeater::make('companyQuestions')
                                    ->relationship()
                                    ->label('Company questions')
                                    ->defaultItems(0)
                                    ->columnSpanFull()
                                    ->schema([
                                        Textarea::make('question')
                                            ->label('Question')
                                            ->required()
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        Textarea::make('answer')
                                            ->label('Answer')
                                            ->required()
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->orderColumn('sort_order')
                                    ->addActionLabel('Add company question')
                                    ->itemLabel(static fn (array $state): ?string => filled($state['question'] ?? null) ? (string) $state['question'] : 'New company question')
                                    ->collapsible()
                                    ->collapsed(),
                            ]),
                    ]),
            ]);
    }

    private static function handleGenerateQuestions(Get $get, Set $set): void
    {
        $description = self::resolveDescription($get, 'ai_description');

        if ($description === null) {
            self::notifyDanger('Please provide a position description first.');

            return;
        }

        $level = self::resolveLevel($get);

        if ($level === null) {
            self::notifyDanger('Please select a position level before generating questions.');

            return;
        }

        try {
            /** @var QuestionGenerator $questionGenerator */
            $questionGenerator = app(QuestionGenerator::class);

            $generatedQuestions = $questionGenerator->generate([
                'description' => $description,
                'level' => $level,
                'questions_count' => self::normalizeQuestionsCount($get('ai_questions_count')),
                'focus' => self::normalizeFocus($get('ai_focus')),
                'answer_time_seconds' => self::resolveAnswerTime($get),
                'title' => $get('title'),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            self::notifyDanger('Unable to generate questions right now.', 'Please try again in a moment.');

            return;
        }

        $set('questions', $generatedQuestions);
        self::notifySuccess('Questions generated successfully.');
    }

    private static function handleGenerateCompanyQuestions(Get $get, Set $set): void
    {
        $description = self::resolveDescription($get, 'ai_company_description');

        if ($description === null) {
            self::notifyDanger('Please provide a company or vacancy description first.');

            return;
        }

        try {
            /** @var CompanyQuestionsGenerator $companyQuestionsGenerator */
            $companyQuestionsGenerator = app(CompanyQuestionsGenerator::class);

            $generatedCompanyQuestions = $companyQuestionsGenerator->generate([
                'description' => $description,
                'title' => $get('title'),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            self::notifyDanger('Unable to generate company questions right now.', 'Please try again in a moment.');

            return;
        }

        $set('companyQuestions', $generatedCompanyQuestions);
        self::notifySuccess('Company questions generated successfully.');
    }

    private static function resolveDescription(Get $get, string $fieldPath): ?string
    {
        $description = $get($fieldPath);

        if (! is_string($description)) {
            return null;
        }

        $description = trim($description);

        return $description === '' ? null : $description;
    }

    private static function resolveLevel(Get $get): ?string
    {
        foreach (self::LEVEL_STATE_PATHS as $levelPath) {
            $levelState = $get($levelPath);
            $level = self::normalizeLevel($levelState);

            if ($level !== null) {
                return $level;
            }
        }

        return null;
    }

    private static function normalizeLevel(mixed $value): ?string
    {
        $normalized = match (true) {
            $value instanceof PositionLevel => $value->value,
            $value instanceof BackedEnum => (string) $value->value,
            is_string($value) => trim($value),
            default => '',
        };

        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeQuestionsCount(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 5;
    }

    private static function resolveAnswerTime(Get $get): int
    {
        foreach (self::ANSWER_TIME_STATE_PATHS as $answerTimePath) {
            $answerTime = self::normalizeAnswerTime($get($answerTimePath));

            if ($answerTime !== null) {
                return $answerTime;
            }
        }

        return PositionAnswerTime::TwoMinutesThirtySeconds->value;
    }

    private static function normalizeAnswerTime(mixed $value): ?int
    {
        $normalized = match (true) {
            $value instanceof PositionAnswerTime => $value->value,
            $value instanceof BackedEnum => is_numeric($value->value) ? (int) $value->value : null,
            is_int($value) => $value,
            is_numeric($value) => (int) $value,
            default => null,
        };

        return PositionAnswerTime::tryFrom((int) $normalized)?->value;
    }

    private static function normalizeFocus(mixed $value): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : 'hard_skills';
    }

    private static function notifyDanger(string $title, ?string $body = null): void
    {
        $notification = Notification::make()
            ->title($title)
            ->danger();

        if (is_string($body) && $body !== '') {
            $notification->body($body);
        }

        $notification->send();
    }

    private static function notifySuccess(string $title): void
    {
        Notification::make()
            ->title($title)
            ->success()
            ->send();
    }
}
