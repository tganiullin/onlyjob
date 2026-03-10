<?php

namespace App\Filament\Resources\Positions\Tables;

use App\Models\Position;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PositionsTable
{
    public static function configure(Table $table): Table
    {
        $hasPublicLink = static fn (?Position $record): bool => $record?->is_public === true
            && filled($record?->public_url);

        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->badge()
                    ->sortable(),
                TextColumn::make('is_public')
                    ->label('Visibility')
                    ->state(static fn (?Position $record): string => $record?->is_public ? 'Public' : 'Private')
                    ->badge()
                    ->color(static fn (?Position $record): string => $record?->is_public ? 'success' : 'gray'),
                TextColumn::make('minimum_score')
                    ->label('Minimum score')
                    ->badge()
                    ->sortable(),
                TextColumn::make('answer_time_seconds')
                    ->label('Time per answer')
                    ->badge()
                    ->sortable(),
                TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Questions')
                    ->sortable(),
                TextColumn::make('public_link')
                    ->label('Public link')
                    ->state(static fn (?Position $record): ?string => $record?->public_url)
                    ->copyable()
                    ->copyMessage('Public link copied')
                    ->copyMessageDuration(1500)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($hasPublicLink),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('copyPublicLink')
                    ->label('Copy public link')
                    ->icon('heroicon-m-link')
                    ->visible($hasPublicLink)
                    ->schema([
                        TextInput::make('public_link')
                            ->label('Public link')
                            ->readOnly()
                            ->dehydrated(false)
                            ->copyable(copyMessage: 'Public link copied', copyMessageDuration: 1500),
                    ])
                    ->fillForm(static fn (?Position $record): array => [
                        'public_link' => $record?->public_url,
                    ])
                    ->modalSubmitAction(false)
                    ->action(static function (): void {}),
                EditAction::make(),
                DeleteAction::make()
                    ->label('Archive'),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Archive selected'),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
