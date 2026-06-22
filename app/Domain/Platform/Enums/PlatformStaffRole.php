<?php

namespace App\Domain\Platform\Enums;

enum PlatformStaffRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدیر کل',
            self::Admin => 'کاربر اداری',
            self::Support => 'پشتیبان',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function isAdminPortal(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function isSupportPortal(): bool
    {
        return $this === self::Support;
    }

    /** @return array<int, string> */
    public static function creatableBySuperAdmin(): array
    {
        return [self::Admin->value, self::Support->value];
    }
}
