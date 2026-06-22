<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('phone', '09128916390')->exists()) {
            return;
        }

        $user = User::create([
            'name' => 'مدیر سیستم',
            'email' => 'admin@rahbar.test',
            'phone' => '09128916390',
            'password' => Hash::make('password'),
        ]);

        $tenant = Tenant::create([
            'name' => 'رهبر CRM',
            'slug' => 'rahbar-demo',
            'owner_id' => $user->id,
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $workspace = Workspace::create([
            'tenant_id' => $tenant->id,
            'name' => 'پیش‌فرض',
            'is_default' => true,
        ]);

        $tenant->users()->attach($user->id);
        $workspace->users()->attach($user->id);

        setPermissionsTeamId($tenant->id);
        Role::firstOrCreate([
            'name' => RoleName::Owner->value,
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);
        $user->assignRole(RoleName::Owner->value);

        $user->update([
            'current_tenant_id' => $tenant->id,
            'current_workspace_id' => $workspace->id,
        ]);
    }
}
