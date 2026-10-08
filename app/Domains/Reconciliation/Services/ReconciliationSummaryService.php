<?php

namespace App\Domains\Reconciliation\Services;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;

class ReconciliationSummaryService
{
    public function get(Builder $query): array
    {
        $expected = BigDecimal::zero()->toScale(2);
        $actual = $expected;
        $result = ['total' => 0, 'matched' => 0, 'exceptions' => 0, 'under_review' => 0, 'reconciled' => 0, 'open' => 0];
        foreach ((clone $query)->cursor() as $item) {
            $expected = $expected->plus($item->expected_amount);
            $actual = $actual->plus($item->actual_amount);
            $result['total']++;
            $key = $item->status->value === 'exception' ? 'exceptions' : $item->status->value;
            if (array_key_exists($key, $result)) {
                $result[$key]++;
            }
            if (in_array($item->status->value, ['exception', 'under_review', 'unreconciled'])) {
                $result['open']++;
            }
        }

        return [...$result, 'expected' => (string) $expected, 'actual' => (string) $actual, 'difference' => (string) $expected->minus($actual)->toScale(2), 'currency' => 'NGN'];
    }
}
