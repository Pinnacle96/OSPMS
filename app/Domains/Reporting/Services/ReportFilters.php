<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Parks\Models\Park;
use App\Domains\Reporting\Queries\ReportQuery;
use App\Domains\Reporting\Reports\BaseReport;
use App\Domains\Ticketing\Models\Ticket;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportFilters
{
    public function resolve(User $u, BaseReport $r, array $input): array
    {
        app(ReportCatalog::class)->authorize($u, $r);
        $engine = app(ReportQuery::class);
        $opts = ['status' => $engine->statuses($r, $u), 'channel' => ReportQuery::CHANNELS];
        $rules = ['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'];
        foreach ($r->filters() as $k) {
            if (! isset($rules[$k])) {
                $rules[$k] = isset(ReportQuery::MODELS[$k]) ? 'nullable|ulid' : ['nullable', Rule::in($opts[$k])];
            }
        }
        foreach (array_diff(['lga', 'park', 'revenue_head', 'operator', 'driver', 'vehicle', 'status', 'channel'], $r->filters()) as $k) {
            $rules[$k] = 'prohibited';
        }
        $f = validator($input, $rules)->validate();
        $range = app(DashboardDateRange::class)->resolve($f);
        $f = array_filter($f, fn ($v) => $v !== null && $v !== '');
        $f['from'] = $range['from'];
        $f['to'] = $range['to'];
        foreach (ReportQuery::MODELS as $k => $class) {
            if (isset($f[$k]) && ! $engine->candidates($r, $u, $k)->where('public_id', $f[$k])->exists()) {
                throw ValidationException::withMessages([$k => 'Choose an available value in this report’s current scope.']);
            }
        }
        if (isset($f['park'],$f['lga']) && ! Park::withTrashed()->where('public_id', $f['park'])->whereHas('lga', fn ($q) => $q->withTrashed()->where('public_id', $f['lga']))->exists() && ! $this->historicalPair($r, $u, $f)) {
            throw ValidationException::withMessages(['park' => 'Choose a park in this LGA.']);
        }
        ksort($f);

        return $f;
    }

    private function historicalPair(BaseReport $r, User $u, array $f): bool
    {
        if (! app(ReportQuery::class)->financial($r)) {
            return false;
        }$p = Park::withTrashed()->where('public_id', $f['park'])->value('id');
        $l = Lga::withTrashed()->where('public_id', $f['lga'])->value('id');

        return app(ReportScope::class)->tickets(Ticket::query(), $u)->where('park_id', $p)->where('lga_id', $l)->exists();
    }
}
