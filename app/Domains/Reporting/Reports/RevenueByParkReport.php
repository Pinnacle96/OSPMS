<?php

namespace App\Domains\Reporting\Reports;

class RevenueByParkReport extends BaseReport
{
    public string $type = 'revenue-by-park';

    public string $title = 'Revenue by park';

    public string $family = 'revenue';

    public string $basis = 'Posted NGN ledger credits less linked debits, by occurrence date. Original ticket geography is retained. Payment status is current.';

    public ?string $group = 'park';
}
