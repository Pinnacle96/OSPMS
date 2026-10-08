<?php

namespace App\Domains\Reconciliation\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Services\FinancePeriod;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Identity\Models\User;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StartReconciliationAction
{
    public function execute(User $actor, array $input): ReconciliationRun
    {
        Gate::forUser($actor)->authorize('create', ReconciliationRun::class);
        $period = app(FinancePeriod::class)->validate($input);
        $criteria = ['from' => $period['from'], 'to' => $period['to'], 'lga_id' => ! empty($period['lga_id']) ? (int) $period['lga_id'] : null, 'park_id' => ! empty($period['park_id']) ? (int) $period['park_id'] : null, 'provider' => $period['provider'] ?? null];

        return app(FinancialConfirmationService::class)->execute($actor, $input['idempotency_key'] ?? '', 'reconciliation.start', $criteria, ReconciliationRun::class, function () use ($actor, $period, $criteria) {
            $run = ReconciliationRun::create(['reconciliation_reference' => 'OSPM-REC-'.Str::ulid(), 'period_start' => $period['period_start'], 'period_end' => $period['period_end'], 'lga_id' => $criteria['lga_id'], 'park_id' => $criteria['park_id'], 'provider' => $criteria['provider'], 'status' => 'queued', 'started_by' => $actor->id, 'started_at' => now(), 'created_at' => now()]);
            app(FinancialAuditService::class)->recordEntity($actor, $run, 'reconciliation.started', $run->reconciliation_reference, payload: $criteria);
            activity('reconciliation')->causedBy($actor)->performedOn($run)->log('Reconciliation requested');

            return $run;
        });
    }
}
