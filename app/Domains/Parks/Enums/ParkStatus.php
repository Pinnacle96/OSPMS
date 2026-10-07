<?php

namespace App\Domains\Parks\Enums;

enum ParkStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
