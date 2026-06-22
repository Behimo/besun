<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'label')) {
                $table->string('label')->nullable()->after('name');
            }
            if (! Schema::hasColumn('roles', 'department')) {
                $table->string('department')->nullable()->after('label');
            }
            if (! Schema::hasColumn('roles', 'is_manager')) {
                $table->boolean('is_manager')->default(false)->after('department');
            }
        });

        $now = now();

        $newPermissions = [
            'tasks.assign',
            'tasks.view_team',
            'daily_reports.view_team',
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

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $newPermissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        // Existing manager roles keep their customized permission sets, so the
        // new granular permissions must be attached without a full re-sync.
        $managerRoleIds = DB::table('roles')
            ->whereIn('name', ['owner', 'sales_manager', 'marketing_manager', 'finance_manager', 'admin'])
            ->pluck('id');

        foreach ($managerRoleIds as $roleId) {
            // Only roles that already have explicit permissions need the attach;
            // roles without rows fall back to config defaults at runtime.
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

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('roles', 'label') ? 'label' : null,
                Schema::hasColumn('roles', 'department') ? 'department' : null,
                Schema::hasColumn('roles', 'is_manager') ? 'is_manager' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['tasks.assign', 'tasks.view_team', 'daily_reports.view_team'])
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
