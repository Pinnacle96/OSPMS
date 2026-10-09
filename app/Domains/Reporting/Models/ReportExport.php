<?php

namespace App\Domains\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    protected $table = 'idempotency_keys';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['response_payload' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('exports', fn ($q) => $q->where('operation', 'report_export'));
    }
}
