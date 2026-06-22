<?php

namespace App\Models;

use App\Domain\Platform\Enums\PlatformStaffRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class PlatformStaff extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'platform_staff';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => PlatformStaffRole::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function createdStaff(): HasMany
    {
        return $this->hasMany(self::class, 'created_by');
    }

    public function roleEnum(): PlatformStaffRole
    {
        return $this->role instanceof PlatformStaffRole
            ? $this->role
            : PlatformStaffRole::from($this->role);
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleEnum()->isSuperAdmin();
    }

    public function canAccessAdminPortal(): bool
    {
        return $this->roleEnum()->isAdminPortal();
    }

    public function canAccessSupportPortal(): bool
    {
        return $this->roleEnum()->isSupportPortal();
    }
}
