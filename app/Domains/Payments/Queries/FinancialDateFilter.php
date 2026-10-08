<?php

namespace App\Domains\Payments\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class FinancialDateFilter
{
    public function apply(Builder $query, string $column, array $filters): void
    {
        $timezone = $filters['timezone'] ?? 'UTC';
        if (! empty($filters['from'])) {
            $query->where($column, '>=', CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where($column, '<', CarbonImmutable::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc());
        }
        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }
    }
}
