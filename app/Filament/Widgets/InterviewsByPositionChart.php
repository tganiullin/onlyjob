<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Widgets\ChartWidget;

class InterviewsByPositionChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Интервью по позициям (топ-10)';

    protected ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $positions = Interview::query()
            ->join('positions', 'interviews.position_id', '=', 'positions.id')
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
