<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class InterviewsByPositionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Интервью по позициям (топ-10)';

    protected ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $positionId = $this->pageFilters['position_id'] ?? null;

        $positions = Interview::query()
            ->join('positions', 'interviews.position_id', '=', 'positions.id')
            ->whereNull('positions.deleted_at')
            ->when($startDate, fn (Builder $query) => $query->whereDate('interviews.created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('interviews.created_at', '<=', $endDate))
            ->when($positionId, fn (Builder $query) => $query->where('interviews.position_id', $positionId))
            ->selectRaw('positions.title, COUNT(*) as count')
            ->groupBy('positions.id', 'positions.title')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'title');

        return [
            'datasets' => [
                [
                    'label' => 'Интервью',
                    'data' => $positions->values()->all(),
                    'backgroundColor' => [
                        '#f59e0b', '#3b82f6', '#22c55e', '#ef4444', '#8b5cf6',
                        '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16',
                    ],
                ],
            ],
            'labels' => $positions->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
