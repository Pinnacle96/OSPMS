<?php

namespace App\Domains\Reporting\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class DashboardDateRange
{
    public function resolve(array $filters): array
    {
        $today = CarbonImmutable::now(config('ospm.timezone'));
        $from = $filters['from'] ?? ($filters['to'] ?? $today->startOfMonth()->toDateString());
        $to = $filters['to'] ?? $today->toDateString();
        $range = $this->between($from, $to);
        if ($from > $to || CarbonImmutable::parse($from, 'UTC')->diffInDays(CarbonImmutable::parse($to, 'UTC')->addDay()) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered date range of at most 366 days.']);
        }

        return $range;
    }

    public function between(string $from, string $to): array
    {
        $timezone = config('ospm.timezone');

        return ['from' => $from, 'to' => $to,
            'start_utc' => CarbonImmutable::parse($from, $timezone)->startOfDay()->utc(),
            'end_utc' => CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc(),
        ];
    }

    public function dayExpression(array $range, string $driver): string
    {
        $column = 'financial_transactions.occurred_at';
        $zone = new \DateTimeZone(config('ospm.timezone'));
        $transitions = $zone->getTransitions($range['start_utc']->timestamp, $range['end_utc']->timestamp);
        $initial = $transitions[0]['offset'] ?? CarbonImmutable::parse($range['start_utc'])->setTimezone($zone)->utcOffset() * 60;
        $cases = [];
        foreach (array_reverse(array_slice($transitions ?: [], 1)) as $transition) {
            $at = gmdate('Y-m-d H:i:s', $transition['ts']);
            $cases[] = "WHEN {$column} >= '{$at}' THEN ".(int) $transition['offset'];
        }
        $offset = $cases ? 'CASE '.implode(' ', $cases).' ELSE '.(int) $initial.' END' : (string) (int) $initial;

        return $driver === 'mysql'
            ? "DATE(DATE_ADD({$column}, INTERVAL ({$offset}) SECOND))"
            : "DATE({$column}, CAST(({$offset}) AS TEXT) || ' seconds')";
    }
}
