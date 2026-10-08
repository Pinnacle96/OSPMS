<?php

namespace App\Domains\Drivers\Enums;

enum DriverStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Blacklisted = 'blacklisted';
    case Inactive = 'inactive';
}
