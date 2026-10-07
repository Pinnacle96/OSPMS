<?php

namespace App\Domains\Identity\Models;

use App\Domains\Identity\Enums\AccessLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOperatorAccess extends Model
{
    protected $table = 'user_operator_access';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['access_level' => AccessLevel::class, 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
