<?php

namespace App\Application\User;

use App\Infrastructure\Persistence\Eloquent\Models\EmployerReview;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfile;
use App\Models\User;
use App\Support\PhoneNormalizer;

class UserProfileService
{
    public function getSelfProfile(User $user): array
    {
        $user->load('profile');

        $reviews = EmployerReview::query()
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->get();

        return [
            'user' => $this->formatUserBase($user),
            'profile' => $this->formatProfileFields($user->profile),
            'tenant_history' => $this->formatTenantHistory($user),
            'reviews' => $this->formatReviews($user),
            'stats' => [
                'tenant_count' => $user->tenants()->count(),
                'review_count' => $reviews->count(),
                'average_rating' => $reviews->count() ? round($reviews->avg('rating'), 1) : null,
                'completion_percent' => $this->completionPercent($user),
            ],
        ];
    }

    public function updateSelfProfile(User $user, array $data): array
    {
        if (array_key_exists('name', $data)) {
            $user->update(['name' => $data['name']]);
        }

        $profileData = collect($data)->only([
            'job_title',
            'city',
            'bio',
            'skills',
            'visible_to_owners',
        ])->all();

        if ($profileData !== []) {
            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData,
            );
        }

        return $this->getSelfProfile($user->fresh(['profile']));
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

    protected function formatProfileFields(?UserProfile $profile): array
    {
        return [
            'job_title' => $profile?->job_title,
            'city' => $profile?->city,
            'bio' => $profile?->bio,
            'skills' => $profile?->skills ?? [],
            'visible_to_owners' => $profile?->visible_to_owners ?? true,
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

    protected function completionPercent(User $user): int
    {
        $profile = $user->profile;
        $fields = [
            $user->name && $user->name !== 'کاربر '.substr($user->phone ?? '', -4),
            filled($profile?->job_title),
            filled($profile?->city),
            filled($profile?->bio),
            is_array($profile?->skills) && count($profile->skills) > 0,
        ];

        return (int) round((count(array_filter($fields)) / count($fields)) * 100);
    }

    protected function roleLabel(?string $role): string
    {
        return match ($role) {
            'owner' => 'مالک',
            'admin' => 'مدیر',
            'employee' => 'کارمند',
            'support' => 'پشتیبانی',
            default => $role ?? '—',
        };
    }
}
