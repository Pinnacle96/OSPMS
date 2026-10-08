<?php

namespace App\Domains\Reconciliation\Enums;

enum ReconciliationRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithExceptions = 'completed_with_exceptions';
    case Failed = 'failed';
}
