<?php

namespace App\Application\User;

use App\Infrastructure\Persistence\Eloquent\Models\EmployerReview;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use App\Support\UserPhoneLookup;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;

class PlatformUserProfileService
{
    public function searchByPhone(string $phone, Tenant $viewerTenant): array
    {
        $normalized = UserPhoneLookup::normalizeOrFail($phone);
        $user = UserPhoneLookup::findByPhone($normalized);

        return [
            'found' => $user !== null,
            'phone' => $normalized,
            'phone_display' => PhoneNormalizer::display($normalized),
            'user' => $user ? $this->formatSearchResult($user, $viewerTenant) : null,
        ];
    }

    public function getProfile(User $user, Tenant $viewerTenant): array
    {
        $user->load(['profile']);

        return [
            'user' => $this->formatUserBase($user),
            'profile' => $this->formatProfile($user),
            'tenant_history' => $this->formatTenantHistory($user),
            'reviews' => $this->formatReviews($user),
            'viewer_context' => $this->formatViewerContext($user, $viewerTenant),
        ];
    }

    public function upsertReview(Tenant $tenant, User $subject, User $reviewer, array $data): EmployerReview
    {
        if (! $tenant->isManagerOrOwner($reviewer)) {
            throw new RuntimeException('فقط مالک یا مدیر می‌تواند نظر ثبت کند');
        }

        if (! $this->wasMemberOfTenant($subject, $tenant)) {
            throw new RuntimeException('این کاربر سابقه همکاری با مجموعه شما ندارد');
        }

        setPermissionsTeamId($tenant->id);
        $role = $subject->roles()->first()?->name;

        return EmployerReview::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $subject->id,
            ],
            [
                'reviewer_id' => $reviewer->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'role_at_review' => $role,
                'is_public' => $data['is_public'] ?? true,
            ],
        );
    }

    protected function formatSearchResult(User $user, Tenant $viewerTenant): array
    {
        $reviews = EmployerReview::query()
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->get();

        $isMember = $viewerTenant->users()->where('users.id', $user->id)->exists();
        $pendingInvite = $viewerTenant->invitations()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($user) {
                $q->where('invited_user_id', $user->id)
                    ->orWhere('invited_phone', $user->phone);
            })
            ->exists();

        return [
            ...$this->formatUserBase($user),
            'profile' => $this->formatProfile($user),
            'tenant_count' => $user->tenants()->count(),
            'review_count' => $reviews->count(),
            'average_rating' => $reviews->count() ? round($reviews->avg('rating'), 1) : null,
            'is_member' => $isMember,
            'has_pending_invite' => $pendingInvite,
        ];
    }

    protected function formatUserBase(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => PhoneNormalizer::display($user->phone),
            'avatar' => $user->avatar,
            'member_since' => persianDateShort($user->created_at),
        ];
    }

    protected function formatProfile(User $user): ?array
    {
        $profile = $user->profile;

        if (! $profile) {
            return null;
        }

        if (! $profile->visible_to_owners) {
            return [
                'hidden' => true,
                'job_title' => null,
                'city' => null,
                'bio' => null,
                'skills' => [],
            ];
        }

        return [
            'hidden' => false,
            'job_title' => $profile->job_title,
            'city' => $profile->city,
            'bio' => $profile->bio,
            'skills' => $profile->skills ?? [],
        ];
    }

    protected function formatTenantHistory(User $user): array
    {
        return $user->tenants()
            ->withPivot(['joined_at', 'left_at', 'invited_by'])
            ->orderByDesc('tenant_user.joined_at')
            ->get()
            ->map(function (Tenant $tenant) use ($user) {
                setPermissionsTeamId($tenant->id);
                $role = $user->roles()->first()?->name;

                return [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'role' => $this->roleLabel($role),
                    'joined_at' => $tenant->pivot->joined_at
                        ? persianDateShort($tenant->pivot->joined_at)
                        : null,
                    'left_at' => $tenant->pivot->left_at
                        ? persianDateShort($tenant->pivot->left_at)
                        : null,
                    'is_active' => $tenant->pivot->left_at === null,
                ];
            })
            ->values()
            ->all();
    }

    protected function formatReviews(User $user): array
    {
        return EmployerReview::query()
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->with(['tenant:id,name', 'reviewer:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EmployerReview $review) => [
                'id' => $review->id,
                'tenant_name' => $review->tenant->name,
                'reviewer_name' => $review->reviewer->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'role_at_review' => $this->roleLabel($review->role_at_review),
                'created_at' => persianDateShort($review->created_at),
            ])
            ->values()
            ->all();
    }

    protected function formatViewerContext(User $user, Tenant $viewerTenant): array
    {
        $isMember = $viewerTenant->users()->where('users.id', $user->id)->exists();
        $pendingInvite = $viewerTenant->invitations()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($user) {
                $q->where('invited_user_id', $user->id)
                    ->orWhere('invited_phone', $user->phone);
            })
            ->exists();

        $existingReview = EmployerReview::query()
            ->where('tenant_id', $viewerTenant->id)
            ->where('user_id', $user->id)
            ->first();

        return [
            'is_member' => $isMember,
            'has_pending_invite' => $pendingInvite,
            'can_invite' => ! $isMember && ! $pendingInvite,
            'can_review' => $this->wasMemberOfTenant($user, $viewerTenant),
            'existing_review' => $existingReview ? [
                'rating' => $existingReview->rating,
                'comment' => $existingReview->comment,
                'is_public' => $existingReview->is_public,
            ] : null,
        ];
    }

    protected function wasMemberOfTenant(User $user, Tenant $tenant): bool
    {
        return DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    protected function roleLabel(?string $role): string
    {
        $enum = \App\Domain\Shared\Enums\RoleName::tryFrom($role ?? '');

        if ($enum) {
            return $enum->label();
        }

        return $role ?? '—';
    }
}
