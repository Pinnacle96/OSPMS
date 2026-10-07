<?php

namespace App\Domains\Routes\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ParkRoute extends Pivot
{
    protected $table = 'park_route';

    public $incrementing = true;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function getUpdatedAtColumn()
    {
        return null;
    }
}
