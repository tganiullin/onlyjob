<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InterviewStatus: string implements HasColor, HasLabel
{
    case PendingConfirmation = 'pending_confirmation';
    case PendingInterview = 'pending_interview';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case QueuedForReview = 'queued_for_review';
    case Reviewing = 'reviewing';
    case ReviewedPassed = 'reviewed_passed';
    case ReviewedFailed = 'reviewed_failed';
    case ReviewFailed = 'review_failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingConfirmation => 'Ожидает подтверждения Telegram',
            self::PendingInterview => 'Готово к интервью',
            self::InProgress => 'Интервью в процессе',
            self::Completed => 'Ожидает AI-проверки',
            self::QueuedForReview => 'В очереди на AI-проверку',
            self::Reviewing => 'AI проверяет интервью',
            self::ReviewedPassed => 'Проверено: порог пройден',
            self::ReviewedFailed => 'Проверено: порог не пройден',
            self::ReviewFailed => 'Ошибка AI-проверки',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingConfirmation => 'gray',
            self::PendingInterview => 'info',
            self::InProgress => 'warning',
            self::Completed => 'info',
            self::QueuedForReview => 'info',
            self::Reviewing => 'warning',
            self::ReviewedPassed => 'success',
            self::ReviewedFailed => 'danger',
            self::ReviewFailed => 'danger',
        };
    }
}
