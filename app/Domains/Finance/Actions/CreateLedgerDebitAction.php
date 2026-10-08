<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Services\LedgerService;
use App\Domains\Identity\Models\User;

class CreateLedgerDebitAction
{
    public function execute(User $actor, FinancialTransaction $parent, string $reference, string $amount, string $type, string $reason): FinancialTransaction
    {
        return app(LedgerService::class)->appendCorrection($actor, $parent, $reference, $amount, $type, $reason, 'debit');
    }
}
