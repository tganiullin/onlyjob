<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InterviewIntegrityEventType: string implements HasLabel
{
    case TabHidden = 'tab_hidden';
    case TabVisible = 'tab_visible';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TabHidden => 'Вкладка скрыта',
            self::TabVisible => 'Возврат во вкладку',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
