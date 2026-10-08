<?php

namespace App\Domains\Vehicles\Enums;

enum VehicleStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Inactive = 'inactive';
}
