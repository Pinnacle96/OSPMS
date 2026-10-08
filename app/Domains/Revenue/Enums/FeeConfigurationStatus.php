<?php

namespace App\Domains\Revenue\Enums;

enum FeeConfigurationStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Inactive = 'inactive';
}
