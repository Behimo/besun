<?php

namespace App\Support;

use App\Domain\Platform\Enums\PlatformStaffRole;
use App\Models\PlatformStaff;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class PlatformAccess
{
    public static function resolve(Authenticatable $actor): ?PlatformStaff
    {
        return $actor instanceof PlatformStaff ? $actor : null;
    }

    public static function isPlatformStaff(?Authenticatable $actor): bool
    {
        return $actor instanceof PlatformStaff;
    }

    public static function isSuperAdmin(?Authenticatable $actor): bool
    {
        return self::resolve($actor)?->isSuperAdmin() ?? false;
    }

    public static function isAdminPortal(?Authenticatable $actor): bool
    {
        return self::resolve($actor)?->canAccessAdminPortal() ?? false;
    }

    public static function isSupportPortal(?Authenticatable $actor): bool
    {
        return self::resolve($actor)?->canAccessSupportPortal() ?? false;
    }

    public static function canManageSms(?Authenticatable $actor): bool
    {
        if (! $actor instanceof PlatformStaff) {
            return false;
        }

        return $actor->canAccessAdminPortal() || $actor->canAccessSupportPortal();
    }

    public static function roleValue(?Authenticatable $actor): ?string
    {
        if (! $actor instanceof PlatformStaff) {
            return null;
        }

        return $actor->roleEnum()->value;
    }

    public static function rejectCustomerUser(?Authenticatable $actor): void
    {
        if ($actor instanceof PlatformStaff) {
            abort(403, 'کاربران پلتفرم باید از پنل اختصاصی وارد شوند.');
        }
    }

    public static function rejectNonPlatformStaff(?Authenticatable $actor): void
    {
        if (! $actor instanceof PlatformStaff) {
            abort(401, 'لطفاً از ورود پنل مدیریت یا پشتیبانی وارد شوید.');
        }
    }
}
