<?php

namespace App\Domains\Reporting\Reports;

class OperatorReport extends BaseReport
{
    public string $type = 'operators';

    public string $title = 'Operators';

    public string $family = 'operators';

    public string $basis = 'Retained operator registrations by creation date, limited to authorized relationships. Private contacts are excluded.';
}
