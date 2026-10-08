<?php

namespace App\Domains\Finance\Enums;

enum AdjustmentStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
