<?php

namespace App\Filament\Resources\Interviews\Pages;

use App\Enums\InterviewStatus;
use App\Filament\Resources\Interviews\InterviewResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditInterview extends EditRecord
{
    protected static string $resource = InterviewResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Status')
                    ->icon(Heroicon::Flag)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('status')
                            ->options(InterviewStatus::class)
                            ->prefixIcon(Heroicon::Flag)
                            ->required(),
                    ]),
                Section::make('Candidate info')
                    ->icon(Heroicon::User)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
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

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
