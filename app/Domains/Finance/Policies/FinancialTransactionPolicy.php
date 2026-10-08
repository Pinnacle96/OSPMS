<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;

class FinancialTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_financial_ledger');
    }

    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $this->viewAny($user) && app(UserAccessScopeService::class)->scopeLedger(FinancialTransaction::query(), $user)->whereKey($transaction->id)->exists();
    }
}
