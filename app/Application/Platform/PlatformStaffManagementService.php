<?php

namespace App\Application\Platform;

use App\Domain\Platform\Enums\PlatformStaffRole;
use App\Models\PlatformStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformStaffManagementService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 50);

        return PlatformStaff::query()
            ->where('role', '!=', PlatformStaffRole::SuperAdmin->value)
            ->when($filters['q'] ?? null, fn ($q, $search) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(PlatformStaff $creator, array $data): PlatformStaff
    {
        $email = mb_strtolower(trim($data['email']));

        if (PlatformStaff::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['این ایمیل قبلاً برای کاربر پلتفرم ثبت شده است.'],
            ]);
        }

        $role = PlatformStaffRole::from($data['role']);

        if (! in_array($role->value, PlatformStaffRole::creatableBySuperAdmin(), true)) {
            throw ValidationException::withMessages([
                'role' => ['نقش انتخاب‌شده مجاز نیست.'],
            ]);
        }

        return PlatformStaff::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => $data['password'],
            'role' => $role->value,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $creator->id,
        ]);
    }

    public function update(PlatformStaff $staff, array $data): PlatformStaff
    {
        if ($staff->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'staff' => ['مدیر کل قابل ویرایش از این بخش نیست.'],
            ]);
        }

        $updates = [];

        if (isset($data['name'])) {
            $updates['name'] = $data['name'];
        }

        if (isset($data['role'])) {
            $role = PlatformStaffRole::from($data['role']);
            if (! in_array($role->value, PlatformStaffRole::creatableBySuperAdmin(), true)) {
                throw ValidationException::withMessages(['role' => ['نقش مجاز نیست.']]);
            }
            $updates['role'] = $role->value;
        }

        if (isset($data['is_active'])) {
            $updates['is_active'] = (bool) $data['is_active'];
        }

        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        if (isset($data['email'])) {
            $email = mb_strtolower(trim($data['email']));
            if (PlatformStaff::where('email', $email)->where('id', '!=', $staff->id)->exists()) {
                throw ValidationException::withMessages(['email' => ['ایمیل تکراری است.']]);
            }
            $updates['email'] = $email;
        }

        $staff->update($updates);

        return $staff->fresh();
    }

    public function format(PlatformStaff $staff): array
    {
        $role = $staff->roleEnum();

        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $role->value,
            'role_label' => $role->label(),
            'is_active' => $staff->is_active,
            'created_at' => $staff->created_at,
        ];
    }
}
