<?php

namespace App\Domains\Reporting\Reports;

class ReconciliationReport extends BaseReport
{
    public string $type = 'reconciliation';

    public string $title = 'Reconciliation';

    public string $family = 'reconciliation';

    public string $basis = 'Retained findings from completed reconciliation runs, by run start date. Each run snapshot is reported separately; totals are findings, not unique obligations.';
}
