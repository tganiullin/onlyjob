<?php

namespace App\Filament\Resources\AiPrompts;

use App\Filament\Resources\AiPrompts\Pages\EditAiPrompt;
use App\Filament\Resources\AiPrompts\Pages\ListAiPrompts;
use App\Filament\Resources\AiPrompts\Pages\ViewAiPrompt;
use App\Filament\Resources\AiPrompts\RelationManagers\VersionsRelationManager;
use App\Models\AiPrompt;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class AiPromptResource extends Resource
{
    protected static ?string $model = AiPrompt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $navigationLabel = 'AI Prompts';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('description')
                    ->label('Description')
                    ->disabled(),
                TextInput::make('feature')
                    ->label('Feature')
                    ->disabled(),
                TextInput::make('type')
                    ->label('Type')
                    ->disabled(),
                Placeholder::make('available_placeholders_display')
                    ->label('Available placeholders')
                    ->content(static function (?AiPrompt $record): string {
                        $placeholders = $record?->available_placeholders ?? [];

                        if ($placeholders === []) {
                            return 'No placeholders';
                        }

                        return collect($placeholders)
                            ->map(static fn (string $p): string => '{{'.$p.'}}')
                            ->implode(', ');
                    }),
                Textarea::make('content')
                    ->label('Prompt content')
                    ->required()
                    ->rows(18)
                    ->columnSpanFull(),
                TextInput::make('change_note')
                    ->label('Change note')
                    ->placeholder('Describe what you changed...')
                    ->maxLength(500)
                    ->dehydrated(false)
                    ->hiddenOn('view'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Prompt')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('feature')
                    ->badge()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Preview')
                    ->state(static fn (AiPrompt $record): string => Str::limit($record->content, 80))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('feature')
                    ->options(static fn (): array => AiPrompt::query()
                        ->distinct()
                        ->pluck('feature', 'feature')
                        ->all()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiPrompts::route('/'),
            'view' => ViewAiPrompt::route('/{record}'),
            'edit' => EditAiPrompt::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
