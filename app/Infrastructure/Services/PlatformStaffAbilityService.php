<?php

namespace App\Infrastructure\Services;

use App\Domain\Platform\Enums\PlatformStaffRole;
use App\Models\PlatformStaff;

class PlatformStaffAbilityService
{
    /** @return array<int, array{action: string, subject: string}> */
    public function rulesFor(PlatformStaff $staff): array
    {
        $role = $staff->roleEnum();

        if ($role === PlatformStaffRole::SuperAdmin) {
            return [
                ['action' => 'manage', 'subject' => 'PlatformSuperAdmin'],
                ['action' => 'manage', 'subject' => 'PlatformAdmin'],
                ['action' => 'manage', 'subject' => 'PlatformSmsQueue'],
                ['action' => 'read', 'subject' => 'PlatformSupport'],
            ];
        }

        if ($role === PlatformStaffRole::Admin) {
            return [
                ['action' => 'manage', 'subject' => 'PlatformAdmin'],
                ['action' => 'manage', 'subject' => 'PlatformSmsQueue'],
                ['action' => 'read', 'subject' => 'PlatformSupport'],
            ];
        }

        return [
            ['action' => 'read', 'subject' => 'PlatformSupport'],
            ['action' => 'manage', 'subject' => 'PlatformSmsQueue'],
        ];
    }
}
