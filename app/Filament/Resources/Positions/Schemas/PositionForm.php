<?php

namespace App\Filament\Resources\Positions\Schemas;

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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Throwable;

class PositionForm
{
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
                Section::make('AI assistant')
                    ->columnSpanFull()
                    ->visibleOn(Operation::Create)
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
                                        $description = $get('ai_description');

                                        if (! is_string($description) || trim($description) === '') {
                                            Notification::make()
                                                ->title('Please provide a position description first.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        $levelState = null;

                                        foreach (['level', '../level', '../../level', '../../../level'] as $levelPath) {
                                            $candidateLevelState = $get($levelPath);

                                            if ($candidateLevelState !== null && $candidateLevelState !== '') {
                                                $levelState = $candidateLevelState;
                                                break;
                                            }
                                        }

                                        $level = match (true) {
                                            $levelState instanceof PositionLevel => $levelState->value,
                                            $levelState instanceof BackedEnum => (string) $levelState->value,
                                            is_string($levelState) => trim($levelState),
                                            default => '',
                                        };

                                        if ($level === '') {
                                            Notification::make()
                                                ->title('Please select a position level before generating questions.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        $questionsCount = $get('ai_questions_count');
                                        $focus = $get('ai_focus');

                                        try {
                                            /** @var QuestionGenerator $questionGenerator */
                                            $questionGenerator = app(QuestionGenerator::class);

                                            $generatedQuestions = $questionGenerator->generate([
                                                'description' => $description,
                                                'level' => $level,
                                                'questions_count' => is_numeric($questionsCount) ? (int) $questionsCount : 5,
                                                'focus' => is_string($focus) ? $focus : 'hard_skills',
                                                'title' => $get('title'),
                                            ]);
                                        } catch (Throwable $exception) {
                                            report($exception);

                                            Notification::make()
                                                ->title('Unable to generate questions right now.')
                                                ->body('Please try again in a moment.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        $set('questions', $generatedQuestions);

                                        Notification::make()
                                            ->title('Questions generated successfully.')
                                            ->success()
                                            ->send();
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
                    ->visibleOn(Operation::Create),
            ]);
    }
}
