<?php

namespace App\Models\Concerns;

trait AssignsSortOrderOnCreate
{
    protected static function bootAssignsSortOrderOnCreate(): void
    {
        static::creating(function ($model): void {
            /** @var self $model */
            $sortOrderColumn = $model->getSortOrderColumnName();
            $groupColumn = $model->getSortOrderGroupColumnName();
            $groupId = $model->getAttribute($groupColumn);
            $sortOrderValue = $model->getAttribute($sortOrderColumn);

            if ($sortOrderValue !== null || ! is_numeric($groupId)) {
                return;
            }

            $maxSortOrder = static::query()
                ->where($groupColumn, (int) $groupId)
                ->max($sortOrderColumn);

            $model->setAttribute($sortOrderColumn, $maxSortOrder + 1);
        });
    }

    protected function getSortOrderColumnName(): string
    {
        return 'sort_order';
    }

    protected function getSortOrderGroupColumnName(): string
    {
        return 'position_id';
    }
}
