<?php

namespace App\Domains\Vehicles\Enums;

enum VehicleType: string
{
    case Bus = 'bus';
    case Minibus = 'minibus';
    case Taxi = 'taxi';
    case Tricycle = 'tricycle';
    case Motorcycle = 'motorcycle';
    case Other = 'other';
}
