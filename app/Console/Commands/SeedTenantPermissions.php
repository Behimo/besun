<?php

namespace App\Console\Commands;

use Database\Seeders\TenantPermissionSeeder;
use Illuminate\Console\Command;

class SeedTenantPermissions extends Command
{
    protected $signature = 'tenants:seed-permissions {--tenant= : Specific tenant ID}';

    protected $description = 'Seed CRM permissions and role defaults for tenant(s)';

    public function handle(TenantPermissionSeeder $seeder): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $seeder->seedForTenant((int) $tenantId);
            $this->info("Permissions seeded for tenant #{$tenantId}");

            return self::SUCCESS;
        }

        $seeder->seedAllTenants();
        $this->info('Permissions seeded for all tenants');

        return self::SUCCESS;
    }
}
