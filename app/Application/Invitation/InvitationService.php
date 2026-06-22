<?php

namespace App\Application\Invitation;

use App\Infrastructure\Persistence\Eloquent\Models\Invitation;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use App\Support\UserPhoneLookup;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;

class InvitationService
{
    public function createInvitation(Tenant $tenant, string $phone, string $role, User $inviter, ?string $department = null): Invitation
    {
        return DB::transaction(function () use ($tenant, $phone, $role, $inviter, $department) {
            if (! $tenant->isManagerOrOwner($inviter)) {
                throw new RuntimeException('شما مجوز دعوت از این مجموعه را ندارید');
            }

            $resolver = app(\App\Infrastructure\Services\PermissionResolverService::class);
            $roleMeta = $resolver->roleMeta($tenant->id, $role);

            if (! $roleMeta
                || $roleMeta['is_owner']
                || ! in_array($role, $resolver->assignableRoleNames($tenant->id), true)) {
                throw new RuntimeException('نقش دعوت نامعتبر است');
            }

            if ($roleMeta['is_manager'] && ! $tenant->isOwner($inviter)) {
                throw new RuntimeException('فقط مالک می‌تواند مدیر دعوت کند');
            }

            $normalizedPhone = UserPhoneLookup::normalizeOrFail($phone);
            $invitedUser = UserPhoneLookup::findByPhone($normalizedPhone);

            if ($invitedUser && $invitedUser->id === $inviter->id) {
                throw new RuntimeException('شما نمی‌توانید خود را دعوت کنید');
            }

            $pending = Invitation::where('tenant_id', $tenant->id)
                ->where('invited_phone', $normalizedPhone)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->exists();

            if ($pending) {
                throw new RuntimeException('یک دعوت‌نامه فعال برای این شماره موبایل وجود دارد');
            }

            if ($invitedUser && $tenant->users()->where('users.id', $invitedUser->id)->exists()) {
                throw new RuntimeException('این کاربر قبلاً به این مجموعه اضافه شده است');
            }

            if (! $tenant->hasAvailableSeat()) {
                $limit = $tenant->seatLimit();
                throw new RuntimeException("سقف کارمندان این مجموعه ({$limit} نفر) تکمیل شده است. برای افزودن کارمند، صندلی بیشتر خریداری کنید.");
            }

            $resolvedDepartment = $department ?? $roleMeta['department'];

            return Invitation::create([
                'tenant_id' => $tenant->id,
                'invited_phone' => $normalizedPhone,
                'invited_user_id' => $invitedUser?->id,
                'role' => $role,
                'department' => $resolvedDepartment,
                'status' => 'pending',
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(7),
            ]);
        });
    }

    public function listForTenant(Tenant $tenant)
    {
        return Invitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['invitedUser', 'inviter'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'invited_phone' => $invitation->invited_phone,
                'role' => $invitation->role,
                'department' => $invitation->department,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at,
                'expires_at_jalali' => persianDateShort($invitation->expires_at),
                'invited_user' => $invitation->invitedUser ? [
                    'id' => $invitation->invitedUser->id,
                    'name' => $invitation->invitedUser->name,
                ] : null,
                'inviter' => $invitation->inviter ? [
                    'id' => $invitation->inviter->id,
                    'name' => $invitation->inviter->name,
                ] : null,
            ]);
    }

    public function listForUser(User $user)
    {
        return Invitation::query()
            ->where(function ($query) use ($user) {
                $query->where('invited_user_id', $user->id);

                if ($user->phone) {
                    $normalized = PhoneNormalizer::normalize($user->phone);
                    if ($normalized) {
                        $query->orWhere('invited_phone', $normalized);
                    }
                }
            })
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['tenant', 'inviter'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function accept(Invitation $invitation, User $user): Invitation
    {
        return DB::transaction(function () use ($invitation, $user) {
            $this->assertInvitee($invitation, $user);

            if ($invitation->isExpired()) {
                $invitation->update(['status' => 'expired']);
                throw new RuntimeException('دعوت‌نامه منقضی شده است');
            }

            $tenant = $invitation->tenant;

            if (! $tenant->users()->where('users.id', $user->id)->exists() && ! $tenant->hasAvailableSeat()) {
                $limit = $tenant->seatLimit();
                throw new RuntimeException("سقف کارمندان این مجموعه ({$limit} نفر) تکمیل شده است.");
            }

            if (! $tenant->users()->where('users.id', $user->id)->exists()) {
                $department = $invitation->department
                    ?? app(\App\Infrastructure\Services\PermissionResolverService::class)
                        ->roleMeta($tenant->id, $invitation->role)['department'] ?? null;

                $tenant->users()->attach($user->id, [
                    'joined_at' => now(),
                    'invited_by' => $invitation->invited_by,
                    'department' => $department,
                    'permission_overrides' => json_encode(['grant' => [], 'revoke' => []]),
                ]);

                $defaultWorkspace = $tenant->workspaces()->where('is_default', true)->first()
                    ?? $tenant->workspaces()->first();

                if ($defaultWorkspace) {
                    $defaultWorkspace->users()->syncWithoutDetaching([$user->id]);
                }
            }

            setPermissionsTeamId($tenant->id);
            $this->ensureRole($invitation->role);
            $user->syncRoles([$invitation->role]);

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'invited_user_id' => $user->id,
            ]);

            return $invitation->fresh(['tenant', 'inviter']);
        });
    }

    public function reject(Invitation $invitation, User $user): Invitation
    {
        $this->assertInvitee($invitation, $user);
        $invitation->update(['status' => 'rejected']);

        return $invitation;
    }

    public function cancel(Invitation $invitation, User $actor): Invitation
    {
        $tenant = $invitation->tenant;

        if (! $tenant->isManagerOrOwner($actor)) {
            throw new RuntimeException('شما مجوز لغو این دعوت‌نامه را ندارید');
        }

        if ($invitation->status !== 'pending') {
            throw new RuntimeException('فقط دعوت‌نامه‌های در انتظار قابل لغو هستند');
        }

        $invitation->update(['status' => 'cancelled']);

        return $invitation;
    }

    protected function assertInvitee(Invitation $invitation, User $user): void
    {
        if ($invitation->status !== 'pending') {
            throw new RuntimeException('دعوت‌نامه معتبر نیست');
        }

        $phoneMatch = UserPhoneLookup::phonesMatch($invitation->invited_phone, $user->phone);
        $userMatch = $invitation->invited_user_id && (int) $invitation->invited_user_id === (int) $user->id;

        if (! $phoneMatch && ! $userMatch) {
            throw new RuntimeException('این دعوت‌نامه برای شما نیست');
        }
    }

    protected function ensureRole(string $name): void
    {
        Role::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
            'tenant_id' => getPermissionsTeamId(),
        ]);
    }
}
