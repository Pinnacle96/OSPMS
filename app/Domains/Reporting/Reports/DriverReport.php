<?php

namespace App\Domains\Reporting\Reports;

class DriverReport extends BaseReport
{
    public string $type = 'drivers';

    public string $title = 'Drivers';

    public string $family = 'drivers';

    public string $basis = 'Retained driver registrations by creation date, limited to authorized relationships. Private contact and document identifiers are excluded.';
}
