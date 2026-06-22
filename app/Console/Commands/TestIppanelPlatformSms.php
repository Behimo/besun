<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\Sms\IppanelHttpClient;
use App\Infrastructure\Services\Sms\IppanelOtpService;
use App\Support\PhoneNormalizer;
use Illuminate\Console\Command;

class TestIppanelPlatformSms extends Command
{
    protected $signature = 'sms:test-platform
                            {--phone= : شماره موبایل برای ارسال تست OTP}
                            {--send : ارسال واقعی پیامک تست}';

    protected $description = 'بررسی پیکربندی IPPanel و اتصال API (اعتبار، ارسال OTP)';

    public function handle(IppanelHttpClient $client, IppanelOtpService $otpService): int
    {
        $this->info('بررسی پیکربندی IPPanel...');
        $this->table(['کلید', 'وضعیت'], [
            ['IPPANEL_BASE_URL', config('sms.base_url') ?: '—'],
            ['IPPANEL_AGENCY_API_KEY', filled(config('sms.agency_api_key')) ? 'تنظیم شده ('.strlen((string) config('sms.agency_api_key')).' کاراکتر)' : 'خالی'],
            ['IPPANEL_OTP_FROM_NUMBER', config('sms.otp_from_number') ?: '—'],
            ['IPPANEL_OTP_PATTERN_CODE', config('sms.otp_pattern_code') ?: '— (webservice)'],
            ['platform configured', IppanelOtpService::isPlatformConfigured() ? 'بله' : 'خیر'],
            ['auth header mode', 'Edge raw key (بدون AccessKey)'],
        ]);

        if (! IppanelOtpService::isPlatformConfigured()) {
            $this->error('پیکربندی ناقص است. مقادیر .env را بررسی کنید و php artisan config:clear را اجرا کنید.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('دریافت موجودی حساب...');

        try {
            $response = $client->get('/api/payment/credit/mine');
            $credit = $response['data']['credit'] ?? null;
            $this->line('موجودی: '.($credit !== null ? number_format((float) $credit).' ریال' : 'نامشخص'));
        } catch (\Throwable $e) {
            $this->error('خطا در اتصال به IPPanel: '.$e->getMessage());
            $this->warn('اگر کلید API تازه اضافه شده، روی سرور اجرا کنید: php artisan config:clear');

            return self::FAILURE;
        }

        if (! $this->option('send')) {
            $this->newLine();
            $this->comment('برای ارسال OTP تست: php artisan sms:test-platform --phone=09xxxxxxxxx --send');

            return self::SUCCESS;
        }

        $phone = (string) $this->option('phone');

        if (! PhoneNormalizer::normalize($phone)) {
            $this->error('شماره موبایل معتبر نیست.');

            return self::FAILURE;
        }

        $code = '123456';
        $this->info('ارسال OTP تست به '.PhoneNormalizer::display($phone).' ...');

        try {
            $sent = $otpService->send($phone, $code);
            $this->line($sent ? 'ارسال موفق.' : 'ارسال انجام نشد (پیکربندی یا خطای API).');

            return $sent ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('خطا: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
