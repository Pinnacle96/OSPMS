<?php

namespace App\Domains\Geography\Models;

use App\Domains\Geography\Enums\LgaStatus;
use App\Domains\Parks\Models\Park;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lga extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id', 'public_id'];

    public function parks(): HasMany
    {
        return $this->hasMany(Park::class);
    }

    protected function casts(): array
    {
        return ['status' => LgaStatus::class];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
