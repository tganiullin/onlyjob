<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InterviewsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Интервью за последние 30 дней';

    protected ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $filterStart = $this->pageFilters['startDate'] ?? null;
        $filterEnd = $this->pageFilters['endDate'] ?? null;

        $startDate = $filterStart ? Carbon::parse($filterStart) : Carbon::today()->subDays(29);
        $endDate = $filterEnd ? Carbon::parse($filterEnd) : Carbon::today();
        $days = (int) $startDate->diffInDays($endDate) + 1;

        $positionId = $this->pageFilters['position_id'] ?? null;

        $counts = Interview::query()
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->when($positionId, fn (Builder $query) => $query->where('position_id', $positionId))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->pluck('count', 'date');

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $labels[] = $date->format('d.m');
            $data[] = (int) ($counts[$date->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Интервью',
                    'data' => $data,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
