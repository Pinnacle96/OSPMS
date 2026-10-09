<?php

namespace App\Domains\Incidents\Enums;

enum IncidentStatus: string
{
    case Reported = 'reported';
    case UnderReview = 'under_review';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
