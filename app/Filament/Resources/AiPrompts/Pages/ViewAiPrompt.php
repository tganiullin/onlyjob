<?php

namespace App\Filament\Resources\AiPrompts\Pages;

use App\Filament\Resources\AiPrompts\AiPromptResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAiPrompt extends ViewRecord
{
    protected static string $resource = AiPromptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
