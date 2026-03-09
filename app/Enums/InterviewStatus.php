<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InterviewStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
        };
    }
}
