<?php

namespace App\Domains\Reconciliation\Enums;

enum ReconciliationItemStatus: string
{
    case Unreconciled = 'unreconciled';
    case Matched = 'matched';
    case Exception = 'exception';
    case UnderReview = 'under_review';
    case Reconciled = 'reconciled';
}
