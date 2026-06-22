<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantChatGroup extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'created_by',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_chat_group_user')
            ->withPivot('joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TenantChatMessage::class, 'group_id');
    }
}
