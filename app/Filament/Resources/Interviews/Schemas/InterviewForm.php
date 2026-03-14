<?php

namespace App\Filament\Resources\Interviews\Schemas;

use App\Enums\InterviewStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->required(),
                        TextInput::make('first_name')
                            ->label('First name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Last name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('telegram')
                            ->label('Telegram')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                    ]),
                Section::make('Interview result')
                    ->schema([
                        Select::make('status')
                            ->options(InterviewStatus::class)
                            ->default(InterviewStatus::Pending->value)
                            ->required(),
                        ToggleButtons::make('candidate_feedback_rating')
                            ->label('Candidate feedback rating')
                            ->options(array_combine(range(1, 5), range(1, 5)))
                            ->inline(),
                        TextInput::make('score')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('summary')
                            ->rows(4)
                            ->columnSpanFull(),
                        DateTimePicker::make('started_at')
                            ->seconds(false),
                        DateTimePicker::make('completed_at')
                            ->seconds(false),
                    ]),
            ]);
    }
}
