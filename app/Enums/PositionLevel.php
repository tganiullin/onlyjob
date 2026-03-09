<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PositionLevel: string implements HasLabel
{
    case Junior = 'junior';
    case Middle = 'middle';
    case Senior = 'senior';
    case Lead = 'lead';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Junior => 'Junior',
            self::Middle => 'Middle',
            self::Senior => 'Senior',
            self::Lead => 'Lead',
        };
    }
}
