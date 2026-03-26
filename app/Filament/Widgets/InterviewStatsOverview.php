<?php

namespace App\Filament\Widgets;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InterviewStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalCount = $this->baseQuery()->count();

        $reviewedStatuses = [InterviewStatus::ReviewedPassed, InterviewStatus::ReviewedFailed];
        $reviewedCount = $this->baseQuery()->whereIn('status', $reviewedStatuses)->count();
        $passedCount = $this->baseQuery()->where('status', InterviewStatus::ReviewedPassed)->count();

        $averageScore = $this->baseQuery()
            ->whereIn('status', $reviewedStatuses)
            ->whereNotNull('score')
            ->avg('score');

        $averageAdequacy = $this->baseQuery()
            ->whereIn('status', $reviewedStatuses)
            ->whereNotNull('adequacy_score')
            ->avg('adequacy_score');

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

            Stat::make('Средняя адекватность', $averageAdequacy !== null ? number_format((float) $averageAdequacy, 1) : '—')
                ->description('По проверенным интервью')
                ->color('warning'),

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

        $query = $this->baseQuery()
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

    /**
     * @return Builder<Interview>
     */
    private function baseQuery(): Builder
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $positionId = $this->pageFilters['position_id'] ?? null;

        return Interview::query()
            ->when($startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $endDate))
            ->when($positionId, fn (Builder $query) => $query->where('position_id', $positionId));
    }
}
