<?php

namespace App\Domains\Finance\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Successful = 'successful';
    case Failed = 'failed';
}
