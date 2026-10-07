<?php

namespace App\Domains\Parks\Models;

use App\Domains\Geography\Models\Lga;
use App\Domains\Parks\Enums\ParkStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Park extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['status' => ParkStatus::class];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }
}
