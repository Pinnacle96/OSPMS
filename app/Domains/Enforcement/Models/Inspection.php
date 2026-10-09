<?php

namespace App\Domains\Enforcement\Models;

use App\Domains\Enforcement\Enums\InspectionResult;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['id', 'public_id'];

    protected function casts(): array
    {
        return ['result' => InspectionResult::class, 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Inspection records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Inspection history must be retained.'));
    }
}
