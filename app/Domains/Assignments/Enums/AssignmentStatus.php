<?php

namespace App\Domains\Assignments\Enums;

enum AssignmentStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Suspended = 'suspended';
}
