<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InterviewStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Passed = 'passed';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
        };
    }
}
