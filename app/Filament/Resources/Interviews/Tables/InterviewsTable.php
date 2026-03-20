<?php

namespace App\Filament\Resources\Interviews\Tables;

use App\Enums\InterviewStatus;
use App\Jobs\CheckInterviewJob;
use App\Models\Interview;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InterviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position.title')
                    ->label('Position')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('candidate')
                    ->label('Candidate')
                    ->state(static fn (Interview $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(query: static function ($query, string $search): void {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    }),
                TextColumn::make('telegram')
                    ->searchable(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
