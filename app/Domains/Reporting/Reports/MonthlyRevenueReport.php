<?php

namespace App\Domains\Reporting\Reports;

class MonthlyRevenueReport extends BaseReport
{
    public string $type = 'monthly-revenue';

    public string $title = 'Monthly revenue';

    public string $family = 'revenue';

    public string $basis = 'Posted NGN ledger credits less linked debits, by occurrence date. Original ticket geography is retained. Payment status is current.';

    public ?string $group = 'month';
}
