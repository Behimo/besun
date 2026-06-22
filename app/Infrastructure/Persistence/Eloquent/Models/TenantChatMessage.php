<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantChatMessage extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'recipient_id',
        'group_id',
        'body',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model): void {
            if (! $model->tenant_id) {
                $model->tenant_id = app(\App\Infrastructure\Services\TenantContext::class)->tenantId();
            }
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TenantChatGroup::class, 'group_id');
    }
}
