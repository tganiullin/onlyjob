<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PositionAnswerTime: int implements HasLabel
{
    case OneMinute = 60;
    case OneMinuteThirtySeconds = 90;
    case TwoMinutes = 120;
    case TwoMinutesThirtySeconds = 150;
    case ThreeMinutes = 180;
    case ThreeMinutesThirtySeconds = 210;
    case FourMinutes = 240;
    case FiveMinutes = 300;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OneMinute => '1 мин',
            self::OneMinuteThirtySeconds => '1 мин 30 сек',
            self::TwoMinutes => '2 мин',
            self::TwoMinutesThirtySeconds => '2 мин 30 сек',
            self::ThreeMinutes => '3 мин',
            self::ThreeMinutesThirtySeconds => '3 мин 30 сек',
            self::FourMinutes => '4 мин',
            self::FiveMinutes => '5 мин',
        };
    }
}
