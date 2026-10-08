<?php

namespace App\Domains\Operators\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OperatorPark extends Pivot
{
    protected $table = 'operator_park';

    public $incrementing = true;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function getUpdatedAtColumn()
    {
        return null;
    }
}
