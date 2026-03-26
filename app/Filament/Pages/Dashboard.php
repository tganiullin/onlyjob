<?php

namespace App\Filament\Pages;

use App\Models\Position;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Дата от'),
                        DatePicker::make('endDate')
                            ->label('Дата до'),
                        Select::make('position_id')
                            ->label('Позиция')
                            ->options(fn () => Position::query()->pluck('title', 'id'))
                            ->searchable()
                            ->placeholder('Все позиции'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
