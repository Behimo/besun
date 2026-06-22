<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlanModule extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'features',
        'category',
        'sort_order',
        'nav_route',
        'icon',
        'price',
        'is_core',
        'monthly_price',
        'semi_annual_price',
        'annual_price',
        'seat_monthly_price',
        'seat_semi_annual_price',
        'seat_annual_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_core' => 'boolean',
            'features' => 'array',
            'price' => 'decimal:0',
            'monthly_price' => 'decimal:0',
            'semi_annual_price' => 'decimal:0',
            'annual_price' => 'decimal:0',
            'seat_monthly_price' => 'decimal:0',
            'seat_semi_annual_price' => 'decimal:0',
            'seat_annual_price' => 'decimal:0',
        ];
    }

    public function getPriceForSubscription(string $type): float
    {
        return match ($type) {
            'monthly' => (float) ($this->monthly_price ?? $this->price),
            'semi_annual' => (float) ($this->semi_annual_price ?? $this->price * 5),
            'annual' => (float) ($this->annual_price ?? $this->price * 10),
            default => (float) ($this->monthly_price ?? $this->price),
        };
    }

    public function getSeatPriceForPeriod(string $period): float
    {
        return match ($period) {
            'monthly' => (float) ($this->seat_monthly_price ?? $this->monthly_price ?? $this->price),
            'semi_annual' => (float) ($this->seat_semi_annual_price ?? ($this->seat_monthly_price ?? $this->monthly_price ?? $this->price) * 5),
            'annual' => (float) ($this->seat_annual_price ?? ($this->seat_monthly_price ?? $this->monthly_price ?? $this->price) * 10),
            default => (float) ($this->seat_monthly_price ?? $this->monthly_price ?? $this->price),
        };
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class, 'subscription_modules', 'plan_module_id', 'subscription_id')
            ->withPivot(['status', 'subscription_type', 'expires_at', 'price_paid', 'purchased_at'])
            ->withTimestamps();
    }
}
