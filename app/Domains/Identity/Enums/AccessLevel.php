<?php

namespace App\Domains\Identity\Enums;

enum AccessLevel: string
{
    case View = 'view';
    case Manage = 'manage';
}
