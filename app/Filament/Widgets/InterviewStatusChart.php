<?php

namespace App\Filament\Widgets;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class InterviewStatusChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Распределение по статусам';

    protected ?string $pollingInterval = '30s';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $positionId = $this->pageFilters['position_id'] ?? null;

        $counts = Interview::query()
            ->when($startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->when($positionId, fn (Builder $query) => $query->where('position_id', $positionId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $labels = [];
        $data = [];
        $colors = [];

        $colorMap = [
            InterviewStatus::PendingConfirmation->value => '#f59e0b',
            InterviewStatus::PendingInterview->value => '#f97316',
            InterviewStatus::InProgress->value => '#3b82f6',
            InterviewStatus::Completed->value => '#8b5cf6',
            InterviewStatus::QueuedForReview->value => '#6366f1',
            InterviewStatus::Reviewing->value => '#a855f7',
            InterviewStatus::ReviewedPassed->value => '#22c55e',
            InterviewStatus::ReviewedFailed->value => '#ef4444',
            InterviewStatus::ReviewFailed->value => '#dc2626',
        ];

        foreach (InterviewStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            if ($count === 0) {
                continue;
            }

            $labels[] = $status->getLabel();
            $data[] = $count;
            $colors[] = $colorMap[$status->value];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                ],
            ],
        ];
    }
}
