<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum QuestionAnswerMode: string implements HasLabel
{
    case Voice = 'voice';
    case Text = 'text';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Voice => 'Voice',
            self::Text => 'Text',
        };
    }
}
