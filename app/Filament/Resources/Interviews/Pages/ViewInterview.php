<?php

namespace App\Filament\Resources\Interviews\Pages;

use App\Enums\InterviewIntegrityEventType;
use App\Enums\InterviewStatus;
use App\Filament\Resources\Interviews\InterviewResource;
use App\Jobs\CheckInterviewJob;
use App\Models\Interview;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ViewInterview extends ViewRecord
{
    protected static string $resource = InterviewResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Candidate')
                    ->icon(Heroicon::User)
                    ->columns(2)
                    ->schema([
                        Select::make('position_id')
                            ->label('Position')
                            ->relationship('position', 'title')
                            ->prefixIcon(Heroicon::Briefcase)
                            ->columnSpanFull(),
                        TextInput::make('first_name')
                            ->label('First name')
                            ->prefixIcon(Heroicon::User),
                        TextInput::make('last_name')
                            ->label('Last name')
                            ->prefixIcon(Heroicon::User),
                        TextInput::make('telegram')
                            ->label('Telegram')
                            ->prefixIcon(Heroicon::PaperAirplane),
                        TextInput::make('email')
                            ->label('Email')
                            ->prefixIcon(Heroicon::Envelope),
                        TextInput::make('phone')
                            ->label('Phone')
                            ->prefixIcon(Heroicon::Phone),
                    ]),
                Section::make('Interview result')
                    ->icon(Heroicon::ChartBar)
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(InterviewStatus::class)
                            ->prefixIcon(Heroicon::Flag),
                        TextInput::make('score')
                            ->prefixIcon(Heroicon::Star),
                        ToggleButtons::make('candidate_feedback_rating')
                            ->label('Candidate feedback')
                            ->options(array_combine(range(1, 5), range(1, 5)))
                            ->inline()
                            ->columnSpanFull(),
                        DateTimePicker::make('started_at')
                            ->seconds(false)
                            ->prefixIcon(Heroicon::PlayCircle),
                        DateTimePicker::make('completed_at')
                            ->seconds(false)
                            ->prefixIcon(Heroicon::CheckCircle),
                        Textarea::make('summary')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Interview questions')
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->schema([
                        Repeater::make('interviewQuestions')
                            ->relationship()
                            ->label('')
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
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('candidate_answer')
                                    ->label('Candidate answer')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                TextInput::make('answer_score')
                                    ->label('Score')
                                    ->prefixIcon(Heroicon::Star),
                                Textarea::make('ai_comment')
                                    ->label('AI comment')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Possible cheating events')
                    ->icon(Heroicon::ShieldExclamation)
                    ->schema([
                        Repeater::make('integrityEvents')
                            ->relationship(
                                modifyQueryUsing: static fn (Builder $query): Builder => $query->limit(50),
                            )
                            ->label('')
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
                                    ->prefixIcon(Heroicon::ExclamationTriangle)
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
                                    ->seconds(false)
                                    ->prefixIcon(Heroicon::Clock),
                                Textarea::make('payload')
                                    ->label('Payload')
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('queueAiReview')
                ->label('Queue AI review')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(static fn (Interview $record): bool => in_array($record->status, [
                    InterviewStatus::Completed,
                    InterviewStatus::ReviewFailed,
                ], true))
                ->action(static function (Interview $record): void {
                    $record->forceFill([
                        'status' => InterviewStatus::QueuedForReview,
                    ])->save();

                    CheckInterviewJob::dispatch($record->id);
                })
                ->successNotificationTitle('Interview has been queued for AI review.'),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
