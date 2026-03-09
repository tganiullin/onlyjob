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
            self::OneMinute => '1 min',
            self::OneMinuteThirtySeconds => '1 min 30 sec',
            self::TwoMinutes => '2 min',
            self::TwoMinutesThirtySeconds => '2 min 30 sec',
            self::ThreeMinutes => '3 min',
            self::ThreeMinutesThirtySeconds => '3 min 30 sec',
            self::FourMinutes => '4 min',
            self::FiveMinutes => '5 min',
        };
    }
}
