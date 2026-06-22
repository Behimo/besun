<?php

namespace App\Application\Platform;

use App\Domain\Platform\Enums\PlatformStaffRole;
use App\Models\PlatformStaff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformStaffAuthService
{
    public function login(string $email, string $password, string $portal): PlatformStaff
    {
        $staff = PlatformStaff::where('email', mb_strtolower(trim($email)))->first();

        if (! $staff || ! $staff->is_active || ! Hash::check($password, $staff->password)) {
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور نادرست است.'],
            ]);
        }

        $role = $staff->roleEnum();

        if ($portal === 'admin' && ! $role->isAdminPortal()) {
            throw ValidationException::withMessages([
                'email' => ['این حساب به پنل مدیریت دسترسی ندارد. از ورود پشتیبانی استفاده کنید.'],
            ]);
        }

        if ($portal === 'support' && ! $role->isSupportPortal()) {
            throw ValidationException::withMessages([
                'email' => ['این حساب به پنل پشتیبانی دسترسی ندارد. از ورود مدیریت استفاده کنید.'],
            ]);
        }

        return $staff;
    }

    public function ensureSuperAdminFromConfig(): PlatformStaff
    {
        $email = mb_strtolower(trim((string) config('platform.super_admin_email')));

        if ($email === '') {
            throw new \RuntimeException('PLATFORM_SUPER_ADMIN_EMAIL در .env تنظیم نشده است.');
        }

        $staff = PlatformStaff::where('email', $email)->first();

        if ($staff) {
            if ($staff->roleEnum() !== PlatformStaffRole::SuperAdmin) {
                $staff->update(['role' => PlatformStaffRole::SuperAdmin->value]);
            }

            return $staff;
        }

        $password = (string) config('platform.super_admin_password');

        if ($password === '') {
            throw new \RuntimeException('PLATFORM_SUPER_ADMIN_PASSWORD در .env تنظیم نشده است.');
        }

        return PlatformStaff::create([
            'name' => (string) config('platform.super_admin_name', 'مدیر کل'),
            'email' => $email,
            'password' => $password,
            'role' => PlatformStaffRole::SuperAdmin->value,
            'is_active' => true,
        ]);
    }
}
