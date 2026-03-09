<?php

namespace App\Filament\Resources\Positions\Pages;

use App\Filament\Resources\Positions\PositionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditPosition extends EditRecord
{
    protected static string $resource = PositionResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                $this->getFormContentComponent()->columnSpan(8),
                $this->getRelationManagersContentComponent()->columnSpan(8),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Archive'),
            RestoreAction::make(),
        ];
    }
}
