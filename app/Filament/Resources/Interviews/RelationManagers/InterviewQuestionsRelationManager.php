<?php

namespace App\Filament\Resources\Interviews\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InterviewQuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interviewQuestions';

    protected static ?string $recordTitleAttribute = 'question_text';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('question_text')
                    ->label('Question')
                    ->disabled()
                    ->dehydrated(false)
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('question_text')
                    ->label('Question')
                    ->limit(90),
                TextColumn::make('candidate_answer')
                    ->label('Candidate answer')
                    ->limit(70),
                TextColumn::make('answer_score')
                    ->label('Answer score')
                    ->sortable(),
                TextColumn::make('ai_comment')
                    ->label('AI comment')
                    ->limit(70),
            ])
            ->defaultSort('sort_order');
    }
}
