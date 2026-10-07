<?php

namespace App\Domains\Operators\Enums;

enum OperatorStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
