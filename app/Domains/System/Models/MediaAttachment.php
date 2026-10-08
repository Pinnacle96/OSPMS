<?php

namespace App\Domains\System\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaAttachment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['disk', 'path', 'file_hash', 'attachable_type', 'attachable_id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
