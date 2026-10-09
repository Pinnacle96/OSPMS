<?php

namespace App\Domains\Complaints\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

class ComplaintNote extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Complaint notes are immutable.'));
        static::deleting(fn () => throw new \LogicException('Complaint notes must be retained.'));
    }
}
