<?php

namespace App\Domains\Reporting\Reports;

class VehicleReport extends BaseReport
{
    public string $type = 'vehicles';

    public string $title = 'Vehicles';

    public string $family = 'vehicles';

    public string $basis = 'Retained vehicle registrations by creation date, limited to authorized relationships. Private contact and document identifiers are excluded.';
}
