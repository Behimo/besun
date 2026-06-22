<?php

namespace App\Console\Commands;

use App\Models\CmsAdmin;
use Illuminate\Console\Command;

class CmsEnsureAdminCommand extends Command
{
    protected $signature = 'cms:ensure-admin';

    protected $description = 'ایجاد یا به‌روزرسانی ادمین CMS از .env';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) config('cms.admin_email', env('CMS_ADMIN_EMAIL', 'admin@bisan.ir'))));
        $password = trim((string) config('cms.admin_password', env('CMS_ADMIN_PASSWORD', 'password')));

        if ($email === '' || $password === '') {
            $this->error('CMS_ADMIN_EMAIL و CMS_ADMIN_PASSWORD در .env تنظیم نشده‌اند.');

            return self::FAILURE;
        }

        CmsAdmin::query()->where('email', '!=', $email)->delete();

        $admin = CmsAdmin::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'مدیر سایت',
                'password' => $password,
            ]
        );

        $this->info("ادمین CMS: {$admin->email} (id: {$admin->id})");

        return self::SUCCESS;
    }
}
