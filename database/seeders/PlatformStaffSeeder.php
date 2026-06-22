<?php

namespace Database\Seeders;

use App\Application\Platform\PlatformStaffAuthService;
use Illuminate\Database\Seeder;

class PlatformStaffSeeder extends Seeder
{
    public function run(): void
    {
        try {
            app(PlatformStaffAuthService::class)->ensureSuperAdminFromConfig();
            $this->command?->info('✅ مدیر کل پلتفرم آماده است.');
        } catch (\Throwable $e) {
            $this->command?->warn('⚠️  مدیر کل پلتفرم: '.$e->getMessage());
        }
    }
}
