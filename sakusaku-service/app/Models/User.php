<?php

namespace App\Models;

use App\Enums\UserLevel;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'tenant_id', 'google_id', 'email', 'name', 'avatar_url',
        'role', 'level', 'google_access_token', 'google_refresh_token',
        'google_token_expires_at', 'last_login_at',
    ];

    protected $hidden = [
        'google_access_token', 'google_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'level' => UserLevel::class,
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasMinLevel(int $level): bool
    {
        return $this->level->value >= $level;
    }
}
