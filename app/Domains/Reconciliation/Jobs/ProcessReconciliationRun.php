<?php

namespace App\Domains\Reconciliation\Jobs;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Reconciliation\Actions\RunReconciliationAction;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessReconciliationRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $runId)
    {
        $this->afterCommit();
    }

    public function handle(RunReconciliationAction $action): void
    {
        $action->execute(ReconciliationRun::findOrFail($this->runId));
    }

    public function failed(?\Throwable $exception): void
    {
        DB::transaction(function () {
            $run = ReconciliationRun::whereKey($this->runId)->lockForUpdate()->first();
            if (! $run || in_array($run->status->value, ['completed', 'completed_with_exceptions'])) {
                return;
            }
            $run->update(['status' => 'failed', 'completed_at' => now(), 'summary' => ['failure' => 'Processing failed; no partial findings committed. Start a new run or retry the retained queue job.']]);
            app(FinancialAuditService::class)->recordEntity($run->actor, $run, 'reconciliation.failed', $run->reconciliation_reference);
        }, 3);
    }
}
