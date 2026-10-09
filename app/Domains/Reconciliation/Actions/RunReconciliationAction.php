<?php

namespace App\Domains\Reconciliation\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Notifications\Services\RecordNotificationService;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use App\Domains\Reconciliation\Services\ReconciliationMatcher;
use App\Domains\Reconciliation\Services\ReconciliationSummaryService;
use App\Notifications\ReconciliationExceptionNotification;
use Illuminate\Support\Facades\DB;

class RunReconciliationAction
{
    public function execute(ReconciliationRun $run): ReconciliationRun
    {
        return DB::transaction(function () use ($run) {
            $run = ReconciliationRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if (in_array($run->status->value, ['completed', 'completed_with_exceptions'])) {
                return $run;
            }
            $run->update(['status' => 'running']);
            $evidence = app(ReconciliationMatcher::class)->match($run);
            $summary = app(ReconciliationSummaryService::class)->get($run->items()->getQuery());
            $run->update(['status' => $summary['exceptions'] ? 'completed_with_exceptions' : 'completed', 'completed_at' => now(), 'summary' => [...$summary, 'evidence' => $evidence, 'basis' => 'One obligation per ticket; expected ticket amount versus recorded settled provider gross. Paid tickets outside the issue period are included when payment/reversal falls in the period. Findings reflect the processing snapshot; rerun as a new request after source changes.']]);
            app(FinancialAuditService::class)->recordEntity($run->actor, $run, 'reconciliation.completed', $run->reconciliation_reference, payload: $summary);

            if ($summary['exceptions']) {
                app(RecordNotificationService::class)->send($run->actor, $run, 'reconciliation', 'exceptions', $run->reconciliation_reference, 'Reconciliation has exceptions for review', ReconciliationExceptionNotification::class);
            }

            return $run;
        }, 3);
    }
}
