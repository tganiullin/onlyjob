<?php

namespace App\Filament\Resources\Positions\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class CompanyQuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'companyQuestions';

    protected static ?string $recordTitleAttribute = 'question';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('question')
                    ->label('Question')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('answer')
                    ->label('Answer')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('question')
                    ->label('Question')
                    ->searchable()
                    ->limit(90),
                TextColumn::make('answer')
                    ->label('Answer')
                    ->limit(90)
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
