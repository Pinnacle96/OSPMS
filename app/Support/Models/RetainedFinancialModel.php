<?php

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Model;

abstract class RetainedFinancialModel extends Model
{
    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Financial records must be retained.'));
    }
}
