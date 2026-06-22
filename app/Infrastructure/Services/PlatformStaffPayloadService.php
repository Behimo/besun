<?php

namespace App\Infrastructure\Services;

use App\Domain\Platform\Enums\PlatformStaffRole;
use App\Models\PlatformStaff;

class PlatformStaffPayloadService
{
    public function __construct(
        protected PlatformStaffAbilityService $abilities,
    ) {}

    public function payload(PlatformStaff $staff): array
    {
        $role = $staff->roleEnum();

        return [
            'userData' => $this->userData($staff),
            'userAbilityRules' => $this->abilities->rulesFor($staff),
        ];
    }

    public function userData(PlatformStaff $staff): array
    {
        $role = $staff->roleEnum();

        return [
            'id' => $staff->id,
            'authType' => 'platform_staff',
            'fullName' => $staff->name,
            'username' => $staff->email,
            'email' => $staff->email,
            'phone' => null,
            'avatar' => null,
            'role' => $role->value,
            'roleLabel' => $role->label(),
            'platformRole' => $role->value,
            'isPlatformSuperAdmin' => $role === PlatformStaffRole::SuperAdmin,
            'isPlatformAdmin' => $role->isAdminPortal(),
            'isPlatformSupport' => $role === PlatformStaffRole::Support,
            'isPlatformStaffAdmin' => $role === PlatformStaffRole::Admin,
            'isPlatformSmsAdmin' => $role->isAdminPortal() || $role === PlatformStaffRole::Support,
            'inTenantShell' => false,
            'currentTenantId' => null,
            'currentWorkspaceId' => null,
            'hasCoreModule' => false,
            'activeModules' => [],
            'tenant' => null,
            'workspace' => null,
            'permissions' => [],
        ];
    }
}
