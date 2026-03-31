<?php

namespace App\Filament\Resources\AiPrompts\Pages;

use App\Filament\Resources\AiPrompts\AiPromptResource;
use App\Models\AiPrompt;
use Filament\Resources\Pages\EditRecord;

class EditAiPrompt extends EditRecord
{
    protected static string $resource = AiPromptResource::class;

    protected function beforeSave(): void
    {
        /** @var AiPrompt $record */
        $record = $this->getRecord();

        $newContent = $this->data['content'] ?? '';

        if ((string) $newContent === $record->content) {
            return;
        }

        $changeNote = $this->data['change_note'] ?? null;

        $record->createVersion(
            is_string($changeNote) && $changeNote !== '' ? $changeNote : null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return AiPromptResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
