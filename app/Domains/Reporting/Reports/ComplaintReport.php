<?php

namespace App\Domains\Reporting\Reports;

class ComplaintReport extends BaseReport
{
    public string $type = 'complaints';

    public string $title = 'Complaints';

    public string $family = 'complaints';

    public string $basis = 'Retained complaints by submission date and current authorized geography or unlocated routing. Contacts, descriptions, evidence and notes are excluded.';
}
