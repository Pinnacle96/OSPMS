<?php

namespace App\Domains\Reporting\Reports;

class TransactionReport extends BaseReport
{
    public string $type = 'transactions';

    public string $title = 'Transactions';

    public string $family = 'payments';

    public string $basis = 'NGN payment attempts by initiation date. Attempted amounts include unsuccessful attempts and are not received revenue.';
}
