<?php

namespace App\Filament\Resources\Interviews\Schemas;

use App\Enums\InterviewIntegrityEventType;
use App\Enums\InterviewStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class InterviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Candidate')
                    ->schema([
                        Select::make('position_id')
                            ->label('Position')
                            ->relationship('position', 'title')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('first_name')
                            ->label('First name')
                            ->disabled()
                            ->dehydrated(false)
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Last name')
                            ->disabled()
                            ->dehydrated(false)
                            ->maxLength(255),
                        TextInput::make('telegram')
                            ->label('Telegram')
                            ->disabled()
                            ->dehydrated(false)
                            ->maxLength(255),
                    ]),
                Section::make('Interview result')
                    ->schema([
                        Select::make('status')
                            ->options(InterviewStatus::class)
                            ->default(InterviewStatus::PendingConfirmation->value)
                            ->required(),
                        ToggleButtons::make('candidate_feedback_rating')
                            ->label('Candidate feedback rating')
                            ->options(array_combine(range(1, 5), range(1, 5)))
                            ->disabled()
                            ->dehydrated(false)
                            ->inline(),
                        TextInput::make('score')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('summary')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(4)
                            ->columnSpanFull(),
                        DateTimePicker::make('started_at')
                            ->disabled()
                            ->dehydrated(false)
                            ->seconds(false),
                        DateTimePicker::make('completed_at')
                            ->disabled()
                            ->dehydrated(false)
                            ->seconds(false),
                    ]),
                Section::make('Interview questions')
                    ->visibleOn(Operation::Edit)
                    ->schema([
                        Repeater::make('interviewQuestions')
                            ->relationship()
                            ->label('Interview questions')
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(static fn (array $state): ?string => filled($state['question_text'] ?? null)
                                ? Str::limit((string) $state['question_text'], 110)
                                : null)
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Textarea::make('question_text')
                                    ->label('Question')
                                    ->readOnly()
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('candidate_answer')
                                    ->label('Candidate answer')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->rows(4)
                                    ->columnSpanFull(),
                                TextInput::make('answer_score')
                                    ->label('Answer score')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10),
                                Textarea::make('ai_comment')
                                    ->label('AI comment')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Possible cheating events')
                    ->visibleOn(Operation::Edit)
                    ->schema([
                        Repeater::make('integrityEvents')
                            ->relationship(
                                modifyQueryUsing: static fn (Builder $query): Builder => $query->limit(50),
                            )
                            ->label('Integrity events')
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(static function (array $state): string {
                                $eventType = (string) ($state['event_type'] ?? '');
                                $occurredAt = (string) ($state['occurred_at'] ?? '-');
                                $eventLabel = InterviewIntegrityEventType::tryFrom($eventType)?->getLabel() ?? ($eventType !== '' ? $eventType : 'Event');

                                return sprintf('[%s] %s', $occurredAt, $eventLabel);
                            })
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextInput::make('event_type')
                                    ->label('Event')
                                    ->readOnly()
                                    ->formatStateUsing(static function (mixed $state): string {
                                        if ($state instanceof InterviewIntegrityEventType) {
                                            return (string) $state->getLabel();
                                        }

                                        if (! is_string($state) || $state === '') {
                                            return '-';
                                        }

                                        return InterviewIntegrityEventType::tryFrom($state)?->getLabel() ?? $state;
                                    }),
                                DateTimePicker::make('occurred_at')
                                    ->label('Occurred at')
                                    ->readOnly()
                                    ->seconds(false),
                                TextInput::make('interview_question_id')
                                    ->label('Interview question ID')
                                    ->disabled()
                                    ->dehydrated(false),
                                Textarea::make('payload')
                                    ->label('Payload')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->rows(5)
                                    ->formatStateUsing(static function (mixed $state): string {
                                        if (! is_array($state) || $state === []) {
                                            return '-';
                                        }

                                        return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
