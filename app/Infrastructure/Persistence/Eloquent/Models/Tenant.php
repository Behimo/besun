<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'status',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot(['joined_at', 'invited_by', 'left_at', 'department', 'permission_overrides'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

    public function seatsUsed(): int
    {
        return $this->users()->count();
    }

    public function pendingInvitationCount(): int
    {
        return $this->invitations()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();
    }

    public function seatsReserved(): int
    {
        return $this->seatsUsed() + $this->pendingInvitationCount();
    }

    public function seatLimit(): ?int
    {
        return $this->subscription?->seat_limit;
    }

    public function hasAvailableSeat(): bool
    {
        $limit = $this->seatLimit();

        if ($limit === null) {
            return true;
        }

        return $this->seatsReserved() < $limit;
    }

    public function coreExpiresAt(): ?Carbon
    {
        $core = $this->activeCoreModule();

        if (! $core?->pivot?->expires_at) {
            return null;
        }

        $expiresAt = $core->pivot->expires_at;

        return $expiresAt instanceof Carbon
            ? $expiresAt
            : Carbon::parse($expiresAt);
    }

    public function coreSubscriptionType(): ?string
    {
        return $this->activeCoreModule()?->pivot?->subscription_type;
    }

    public function coreRemainingDays(): ?int
    {
        $expiresAt = $this->coreExpiresAt();

        if (! $expiresAt || ! $expiresAt->isFuture()) {
            return null;
        }

        return max(1, (int) ceil(now()->diffInSeconds($expiresAt, false) / 86400));
    }

    public function hasActiveSubscription(): bool
    {
        return $this->hasActiveCoreModule();
    }

    public function hasActiveCoreModule(): bool
    {
        $core = $this->activeCoreModule();

        return $core !== null;
    }

    public function activeCoreModule(): ?PlanModule
    {
        $subscription = $this->subscription;

        if (! $subscription) {
            return null;
        }

        return $subscription->modules()
            ->where('plan_modules.is_core', true)
            ->wherePivot('status', 'active')
            ->where(function ($query) {
                $query->whereNull('subscription_modules.expires_at')
                    ->orWhere('subscription_modules.expires_at', '>', now());
            })
            ->first();
    }

    public function hasModule(string $slug): bool
    {
        $subscription = $this->subscription;

        if (! $subscription) {
            return false;
        }

        return $subscription->modules()
            ->where('plan_modules.slug', $slug)
            ->wherePivot('status', 'active')
            ->where(function ($query) {
                $query->whereNull('subscription_modules.expires_at')
                    ->orWhere('subscription_modules.expires_at', '>', now());
            })
            ->exists();
    }

    public function activeModuleSlugs(): array
    {
        $subscription = $this->subscription;

        if (! $subscription) {
            return [];
        }

        return $subscription->modules()
            ->wherePivot('status', 'active')
            ->where(function ($query) {
                $query->whereNull('subscription_modules.expires_at')
                    ->orWhere('subscription_modules.expires_at', '>', now());
            })
            ->pluck('plan_modules.slug')
            ->all();
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->id;
    }

    public function isManagerOrOwner(User $user): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        return app(\App\Infrastructure\Services\PermissionResolverService::class)
            ->isManagerRole($user, $this->id);
    }
}
