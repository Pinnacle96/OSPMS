<?php

namespace App\Domains\Reporting\Reports;

class RevenueByLgaReport extends BaseReport
{
    public string $type = 'revenue-by-lga';

    public string $title = 'Revenue by LGA';

    public string $family = 'revenue';

    public string $basis = 'Posted NGN ledger credits less linked debits, by occurrence date. Original ticket geography is retained. Payment status is current.';

    public ?string $group = 'lga';
}
