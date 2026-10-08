<?php

namespace App\Domains\Finance\Services;

use App\Domains\Parks\Models\Park;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class FinancePeriod
{
    public function validate(array $input): array
    {
        $data = Validator::make($input, ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'], 'lga_id' => ['nullable', 'integer', 'exists:lgas,id'], 'park_id' => ['nullable', 'integer', 'exists:parks,id'], 'provider' => ['nullable', 'in:demo']])->validate();
        $from = CarbonImmutable::parse($data['from'], config('ospm.timezone'))->startOfDay();
        $to = CarbonImmutable::parse($data['to'], config('ospm.timezone'))->addDay()->startOfDay();
        Validator::make(['days' => $from->diffInDays($to), 'scope' => empty($data['park_id']) || empty($data['lga_id']) || Park::whereKey($data['park_id'])->where('lga_id', $data['lga_id'])->exists()], ['days' => 'numeric|max:366', 'scope' => 'accepted'], ['days.max' => 'Choose a period of at most 366 days.', 'scope.accepted' => 'The park must belong to the selected LGA.'])->validate();

        return [...$data, 'period_start' => $from->utc(), 'period_end' => $to->utc()];
    }
}
