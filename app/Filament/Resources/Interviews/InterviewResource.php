<?php

namespace App\Filament\Resources\Interviews;

use App\Enums\InterviewStatus;
use App\Filament\Resources\Interviews\Pages\CreateInterview;
use App\Filament\Resources\Interviews\Pages\EditInterview;
use App\Filament\Resources\Interviews\Pages\ListInterviews;
use App\Filament\Resources\Interviews\Pages\ViewInterview;
use App\Filament\Resources\Positions\PositionResource;
use App\Jobs\CheckInterviewJob;
use App\Models\Interview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InterviewResource extends Resource
{
    protected static ?string $model = Interview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Interviews';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position.title')
                    ->label('Position')
                    ->searchable()
                    ->sortable()
                    ->url(static fn (Interview $record): ?string => $record->position_id
                        ? PositionResource::getUrl('edit', ['record' => $record->position_id])
                        : null)
                    ->color('primary'),
                TextColumn::make('candidate')
                    ->label('Candidate')
                    ->state(static fn (Interview $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(query: static function ($query, string $search): void {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    }),
                TextColumn::make('telegram')
                    ->searchable()
                    ->url(static fn (Interview $record): ?string => filled($record->telegram)
                        ? 'https://t.me/'.ltrim((string) $record->telegram, '@')
                        : null)
                    ->openUrlInNewTab()
                    ->color('primary'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('score')
                    ->badge()
                    ->sortable(),
                TextColumn::make('candidate_feedback_rating')
                    ->label('Candidate feedback')
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->since()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InterviewStatus::class),
                SelectFilter::make('position_id')
                    ->label('Position')
                    ->relationship('position', 'title')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('completed_at')
                    ->label('Completed')
                    ->nullable()
                    ->placeholder('All')
                    ->trueLabel('Completed')
                    ->falseLabel('Not completed'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
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
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInterviews::route('/'),
            'create' => CreateInterview::route('/create'),
            'view' => ViewInterview::route('/{record}'),
            'edit' => EditInterview::route('/{record}/edit'),
        ];
    }
}
