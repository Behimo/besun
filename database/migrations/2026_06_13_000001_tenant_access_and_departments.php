<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            $table->string('department', 32)->nullable()->after('invited_by');
            $table->json('permission_overrides')->nullable()->after('department');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('department', 32)->nullable()->after('role');
        });

        foreach (['contacts', 'leads', 'deals'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('department', 32)->nullable()->after('workspace_id');
            });
        }

        $this->migrateLegacyRoles();
        $this->backfillDepartments();
    }

    public function down(): void
    {
        foreach (['contacts', 'leads', 'deals'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('department');
        });

        Schema::table('tenant_user', function (Blueprint $table) {
            $table->dropColumn(['department', 'permission_overrides']);
        });
    }

    protected function migrateLegacyRoles(): void
    {
        $map = [
            'admin' => 'sales_manager',
            'employee' => 'sales_employee',
            'support' => 'marketing_employee',
        ];

        foreach ($map as $old => $new) {
            DB::table('roles')->where('name', $old)->update(['name' => $new]);
        }

        DB::table('invitations')->where('role', 'admin')->update(['role' => 'sales_manager']);
        DB::table('invitations')->where('role', 'employee')->update(['role' => 'sales_employee']);
    }

    protected function backfillDepartments(): void
    {
        DB::table('tenant_user')
            ->whereNull('department')
            ->whereIn('user_id', function ($q) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'like', '%sales%');
            })
            ->update(['department' => 'sales']);

        DB::table('tenant_user')
            ->whereNull('department')
            ->whereIn('user_id', function ($q) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'like', '%marketing%');
            })
            ->update(['department' => 'marketing']);

        DB::table('tenant_user')
            ->whereNull('department')
            ->whereIn('user_id', function ($q) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'like', '%finance%');
            })
            ->update(['department' => 'finance']);

        DB::table('contacts')->whereNull('department')->update(['department' => 'sales']);
        DB::table('leads')->whereNull('department')->update(['department' => 'marketing']);
        DB::table('deals')->whereNull('department')->update(['department' => 'sales']);

        DB::table('invitations')->whereNull('department')->where('role', 'like', '%sales%')->update(['department' => 'sales']);
        DB::table('invitations')->whereNull('department')->where('role', 'like', '%marketing%')->update(['department' => 'marketing']);
        DB::table('invitations')->whereNull('department')->where('role', 'like', '%finance%')->update(['department' => 'finance']);
    }
};
