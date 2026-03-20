<?php

namespace App\Filament\Resources\Positions\Pages;

use App\Filament\Resources\Positions\PositionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreatePosition extends CreateRecord
{
    protected static string $resource = PositionResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            // ->columns(12)
            ->components([
                $this->getFormContentComponent()->columnSpan(8),
            ]);
    }
}
