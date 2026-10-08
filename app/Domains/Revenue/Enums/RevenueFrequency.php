<?php

namespace App\Domains\Revenue\Enums;

enum RevenueFrequency: string
{
    case Daily = 'daily';
    case PerEntry = 'per_entry';
    case PerTrip = 'per_trip';
    case OneTime = 'one_time';
    case Periodic = 'periodic';
    case Other = 'other';
}
