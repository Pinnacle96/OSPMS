<?php

namespace App\Domains\Complaints\Enums;

enum ComplaintSource: string
{
    case PublicWeb = 'public_web';
    case Staff = 'staff';
    case HelpDesk = 'help_desk';
    case Field = 'field';
}
