<?php

namespace App\Filament\Resources\Interviews\Tables;

use App\Models\Interview;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('email')
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
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
