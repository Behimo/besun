<?php

namespace App\Console\Commands;

use App\Application\Platform\PlatformStaffAuthService;
use Illuminate\Console\Command;

class PlatformEnsureSuperAdminCommand extends Command
{
    protected $signature = 'platform:ensure-super-admin';

    protected $description = 'ایجاد یا به‌روزرسانی مدیر کل پلتفرم از .env';

    public function handle(PlatformStaffAuthService $auth): int
    {
        try {
            $staff = $auth->ensureSuperAdminFromConfig();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("مدیر کل: {$staff->email} (id: {$staff->id})");

        return self::SUCCESS;
    }
}
