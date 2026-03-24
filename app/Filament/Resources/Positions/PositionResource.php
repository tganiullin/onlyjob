<?php

namespace App\Filament\Resources\Positions;

use App\Filament\Resources\Positions\Pages\CreatePosition;
use App\Filament\Resources\Positions\Pages\EditPosition;
use App\Filament\Resources\Positions\Pages\ListPositions;
use App\Filament\Resources\Positions\RelationManagers\CompanyQuestionsRelationManager;
use App\Filament\Resources\Positions\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\Positions\Schemas\PositionForm;
use App\Models\Position;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Positions';

    public static function form(Schema $schema): Schema
    {
        return PositionForm::configure($schema);
    }

    public static function table(Table $table): Table
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
                ActionGroup::make([
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
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Archive selected'),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
            CompanyQuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPositions::route('/'),
            'create' => CreatePosition::route('/create'),
            'edit' => EditPosition::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
