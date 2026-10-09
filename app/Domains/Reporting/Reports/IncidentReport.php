<?php

namespace App\Domains\Reporting\Reports;

class IncidentReport extends BaseReport
{
    public string $type = 'incidents';

    public string $title = 'Incidents';

    public string $family = 'incidents';

    public string $basis = 'Retained incidents by occurrence date and current authorized park geography. Descriptions, evidence and private review notes are excluded.';
}
