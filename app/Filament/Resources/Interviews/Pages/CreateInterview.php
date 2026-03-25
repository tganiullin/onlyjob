<?php

namespace App\Filament\Resources\Interviews\Pages;

use App\Filament\Resources\Interviews\InterviewResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CreateInterview extends CreateRecord
{
    protected static string $resource = InterviewResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Candidate')
                    ->icon(Heroicon::User)
                    ->columns(2)
                    ->schema([
                        Select::make('position_id')
                            ->label('Position')
                            ->relationship('position', 'title', static fn ($query) => $query->withoutTrashed())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->prefixIcon(Heroicon::Briefcase)
                            ->columnSpanFull(),
                        TextInput::make('first_name')
                            ->label('First name')
                            ->prefixIcon(Heroicon::User)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Last name')
                            ->prefixIcon(Heroicon::User)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('telegram')
                            ->label('Telegram')
                            ->prefixIcon(Heroicon::PaperAirplane)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->prefixIcon(Heroicon::Envelope)
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Phone')
                            ->prefixIcon(Heroicon::Phone)
                            ->tel()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
