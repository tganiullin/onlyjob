<?php

namespace App\Filament\Resources\Positions\Schemas;

use App\Enums\PositionAnswerTime;
use App\Enums\PositionLevel;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

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
