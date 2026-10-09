<?php

namespace App\Domains\Complaints\Enums;

enum ComplaintStatus: string
{
    case Submitted = 'submitted';
    case Received = 'received';
    case Assigned = 'assigned';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
