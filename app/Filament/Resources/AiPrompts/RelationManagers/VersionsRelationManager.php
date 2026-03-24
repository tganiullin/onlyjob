<?php

namespace App\Filament\Resources\AiPrompts\RelationManagers;

use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Version history';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version_number')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Content preview')
                    ->state(static fn (AiPromptVersion $record): string => Str::limit($record->content, 100))
                    ->wrap(),
                TextColumn::make('change_note')
                    ->label('Note')
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->placeholder('System'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('version_number', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewContent')
                        ->label('View full content')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            Textarea::make('version_content')
                                ->label('Prompt content')
                                ->rows(16)
                                ->readOnly()
                                ->dehydrated(false),
                        ])
                        ->fillForm(static fn (AiPromptVersion $record): array => [
                            'version_content' => $record->content,
                        ])
                        ->modalSubmitAction(false)
                        ->action(static function (): void {}),
                    Action::make('revert')
                        ->label('Revert to this version')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('The current content will be saved as a new version before reverting.')
                        ->action(function (AiPromptVersion $record): void {
                            /** @var AiPrompt $prompt */
                            $prompt = $this->getOwnerRecord();
                            $prompt->revertToVersion($record);
                        })
                        ->successNotificationTitle('Prompt reverted successfully.'),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->paginated([10, 25, 50]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
