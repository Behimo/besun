<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TenantSmsAccount extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'status',
        'ippanel_user_id',
        'ippanel_username',
        'api_key_encrypted',
        'password_encrypted',
        'default_from_number',
        'acl_id',
        'credit_cached',
        'credit_synced_at',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_cached' => 'decimal:4',
            'credit_synced_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function panelRequest(): HasOne
    {
        return $this->hasOne(TenantSmsPanelRequest::class, 'tenant_id', 'tenant_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ippanel_user_id;
    }
}
