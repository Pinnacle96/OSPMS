<?php

namespace App\Domains\Enforcement\Enums;

enum ViolationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
