<?php

namespace App\Domains\Reporting\Reports;

class DailyRevenueReport extends BaseReport
{
    public string $type = 'daily-revenue';

    public string $title = 'Daily revenue';

    public string $family = 'revenue';

    public string $basis = 'Posted NGN ledger credits less linked debits, by occurrence date. Original ticket geography is retained. Payment status is current.';

    public ?string $group = 'day';
}
