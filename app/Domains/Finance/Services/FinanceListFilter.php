<?php

namespace App\Domains\Finance\Services;

use App\Domains\Payments\Queries\FinancialDateFilter;
use Illuminate\Database\Eloquent\Builder;

class FinanceListFilter
{
    public function dates(Builder $query, string $column, array $filters): Builder
    {
        app(FinancialDateFilter::class)->apply($query, $column, [...$filters, 'timezone' => config('ospm.timezone')]);

        return $query;
    }

    public function sort(Builder $query, array $filters, array $columns, string $default): Builder
    {
        $column = in_array($filters['sort'] ?? '', $columns, true) ? $filters['sort'] : $default;

        return $query->orderBy($column, ($filters['order'] ?? '') === 'asc' ? 'asc' : 'desc')->orderByDesc('id');
    }
}
