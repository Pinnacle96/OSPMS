<?php

namespace App\Domains\Identity\Models;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, HasUlids, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'username', 'phone', 'password', 'status', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'last_login_at' => 'datetime', 'password' => 'hashed', 'status' => UserStatus::class, 'must_change_password' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'username', 'phone', 'status', 'must_change_password'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function lgas(): BelongsToMany
    {
        return $this->belongsToMany(Lga::class, 'user_lga_access')->withPivot('access_level', 'created_at');
    }

    public function parks(): BelongsToMany
    {
        return $this->belongsToMany(Park::class, 'user_park_access')->withPivot('access_level', 'created_at');
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'user_operator_access')->withPivot('access_level', 'created_at');
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }
}
