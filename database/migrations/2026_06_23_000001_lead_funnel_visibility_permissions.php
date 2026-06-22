<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $newPermissions = [
            'deals.view_unassigned',
            'leads.view_unassigned',
            'leads.assign',
        ];

        foreach ($newPermissions as $name) {
            $exists = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->attachPermissions([
            'owner' => $newPermissions,
            'admin' => $newPermissions,
            'sales_manager' => [
                'deals.view_unassigned',
                'leads.read',
                'leads.view_unassigned',
                'leads.assign',
                'marketing_funnel.read',
            ],
            'marketing_manager' => [
                'leads.view_unassigned',
                'leads.assign',
            ],
            'finance_manager' => [
                'leads.assign',
            ],
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['deals.view_unassigned', 'leads.view_unassigned', 'leads.assign'])
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, array<int, string>>  $rolePermissions
     */
    private function attachPermissions(array $rolePermissions): void
    {
        foreach ($rolePermissions as $roleName => $permissions) {
            $roleIds = DB::table('roles')->where('name', $roleName)->pluck('id');

            if ($roleIds->isEmpty()) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissions)
                ->where('guard_name', 'web')
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                $hasAny = DB::table('role_has_permissions')->where('role_id', $roleId)->exists();

                if (! $hasAny) {
                    continue;
                }

                foreach ($permissionIds as $permissionId) {
                    $exists = DB::table('role_has_permissions')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $permissionId)
                        ->exists();

                    if (! $exists) {
                        DB::table('role_has_permissions')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }
            }
        }
    }
};
