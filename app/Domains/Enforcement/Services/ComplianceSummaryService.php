<?php

namespace App\Domains\Enforcement\Services;

use Illuminate\Database\Eloquent\Model;

class ComplianceSummaryService
{
    public function get(Model $r, string $kind, bool $assigned): array
    {
        $checks = [['label' => 'Registration status', 'value' => $r->status->value, 'state' => $r->status->value === ($kind === 'operators' ? 'approved' : 'active') ? 'clear' : 'attention']];
        foreach (match ($kind) {
            'drivers' => ['licence_expiry' => 'Licence expiry'],'vehicles' => ['roadworthiness_expiry' => 'Roadworthiness expiry', 'insurance_expiry' => 'Insurance expiry'],default => []
        } as $column => $label) {
            $date = $r->{$column};
            $checks[] = ['label' => $label, 'value' => $date?->format('Y-m-d') ?? 'Not recorded', 'state' => ! $date ? 'unknown' : ($date->format('Y-m-d') < now(config('ospm.timezone'))->toDateString() ? 'attention' : 'clear')];
        }
        $checks[] = ['label' => 'Current scoped assignment', 'value' => $assigned ? 'Recorded' : 'None recorded', 'state' => $assigned ? 'clear' : 'unknown'];
        $states = array_column($checks, 'state');

        return ['status' => in_array('attention', $states) ? 'attention' : (in_array('unknown', $states) ? 'requires_review' : 'clear'), 'checks' => $checks, 'note' => 'Recorded registration and expiry checks only; this is not a legal clearance or a financial settlement decision.'];
    }
}
