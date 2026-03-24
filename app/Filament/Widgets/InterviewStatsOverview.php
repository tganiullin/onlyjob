<?php

namespace App\Filament\Widgets;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class InterviewStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalCount = Interview::query()->count();

        $reviewedStatuses = [InterviewStatus::ReviewedPassed, InterviewStatus::ReviewedFailed];
        $reviewedCount = Interview::query()->whereIn('status', $reviewedStatuses)->count();
        $passedCount = Interview::query()->where('status', InterviewStatus::ReviewedPassed)->count();

        $averageScore = Interview::query()
            ->whereIn('status', $reviewedStatuses)
            ->whereNotNull('score')
            ->avg('score');

        $passRate = $reviewedCount > 0
            ? round($passedCount / $reviewedCount * 100, 1)
            : 0;

        return [
            Stat::make('Всего интервью', $totalCount)
                ->description('За всё время')
                ->chart($this->getDailyCountsForLastDays(7))
                ->color('primary'),

            Stat::make('Проверено', $reviewedCount)
                ->description("Из {$totalCount} всего")
                ->chart($this->getDailyCountsForLastDays(7, $reviewedStatuses))
                ->color('success'),

            Stat::make('Средний балл', $averageScore !== null ? number_format((float) $averageScore, 1) : '—')
                ->description('По проверенным интервью')
                ->color('info'),

            Stat::make('Процент прошедших', "{$passRate}%")
                ->description("{$passedCount} из {$reviewedCount}")
                ->color($passRate >= 50 ? 'success' : 'danger'),
        ];
    }

    /**
     * @param  list<InterviewStatus>|null  $statuses
     * @return list<int>
     */
    private function getDailyCountsForLastDays(int $days, ?array $statuses = null): array
    {
        $startDate = Carbon::today()->subDays($days - 1);

        $query = Interview::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)');

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        $counts = $query->pluck('count', 'date');

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $result[] = (int) ($counts[$date] ?? 0);
        }

        return $result;
    }
}
