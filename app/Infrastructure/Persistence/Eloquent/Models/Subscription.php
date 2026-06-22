<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'seat_limit',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(PlanModule::class, 'subscription_modules', 'subscription_id', 'plan_module_id')
            ->withPivot(['status', 'subscription_type', 'expires_at', 'price_paid', 'purchased_at'])
            ->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class, 'tenant_id', 'tenant_id');
    }
}
