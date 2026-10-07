<?php

namespace App\Domains\Routes\Enums;

enum RouteStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
