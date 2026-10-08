<?php

namespace App\Domains\Reporting\Queries;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerAggregateQuery
{
    public function get(Builder $query, array $groups = []): Collection
    {
        $sum = 'SUM';
        if (DB::getDriverName() === 'sqlite') {
            // SQLite's native SUM converts DECIMAL to floating point. Keep the test adapter exact.
            DB::connection()->getPdo()->sqliteCreateAggregate('ospm_decimal_sum',
                fn ($context, $count, $value) => (string) BigDecimal::of($context ?? '0')->plus((string) ($value ?? '0')),
                fn ($context, $count) => (string) BigDecimal::of($context ?? '0')->toScale(2), 1);
            $sum = 'ospm_decimal_sum';
        }
        $query->selectRaw("COUNT(*) AS transactions, {$sum}(CASE WHEN financial_transactions.direction = 'credit' THEN financial_transactions.amount ELSE 0 END) AS gross, {$sum}(CASE WHEN financial_transactions.direction = 'debit' THEN financial_transactions.amount ELSE 0 END) AS debits");
        foreach ($groups as $alias => $expression) {
            $query->selectRaw($expression.' AS '.$alias)->groupByRaw($expression);
        }

        return $query->get()->map(function ($row) use ($groups) {
            $gross = BigDecimal::of((string) ($row->getRawOriginal('gross') ?? '0'))->toScale(2);
            $debits = BigDecimal::of((string) ($row->getRawOriginal('debits') ?? '0'))->toScale(2);

            return [...collect(array_keys($groups))->mapWithKeys(fn ($key) => [$key => $row->getRawOriginal($key)])->all(),
                'gross' => (string) $gross, 'debits' => (string) $debits, 'net' => (string) $gross->minus($debits),
                'transactions' => (int) $row->getRawOriginal('transactions'),
            ];
        });
    }
}
