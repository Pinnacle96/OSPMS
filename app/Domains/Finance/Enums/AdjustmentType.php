<?php

namespace App\Domains\Finance\Enums;

enum AdjustmentType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
