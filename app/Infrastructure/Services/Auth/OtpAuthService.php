<?php

namespace App\Infrastructure\Services\Auth;

use App\Application\Auth\FindOrCreateUserByPhoneUseCase;
use App\Models\User;
use App\Infrastructure\Services\Sms\IppanelOtpService;
use App\Support\PhoneNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpAuthService
{
    protected bool $smsNotConfigured = false;

    protected ?string $lastSmsError = null;

    public function __construct(
        protected FindOrCreateUserByPhoneUseCase $findOrCreateUser,
        protected IppanelOtpService $ippanelOtp,
    ) {}

    public function send(string $phone): array
    {
        try {
            return $this->sendInternal($phone);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OTP send infrastructure failure', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw ValidationException::withMessages([
                'phone' => [$this->infrastructureFailureMessage($e)],
            ]);
        }
    }

    protected function sendInternal(string $phone): array
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'phone' => ['شماره موبایل معتبر نیست.'],
            ]);
        }

        $user = $this->findOrCreateUser->execute($phone);

        $cooldownKey = $this->cooldownCacheKey($normalized);

        if (Cache::has($cooldownKey)) {
            $retryAfter = Cache::get($cooldownKey) - time();

            throw ValidationException::withMessages([
                'phone' => ["لطفاً {$retryAfter} ثانیه دیگر دوباره تلاش کنید."],
            ]);
        }

        $code = $this->generateCode();
        $ttl = config('otp.ttl_seconds', 300);

        Cache::put($this->otpCacheKey($normalized), [
            'code' => $code,
            'user_id' => $user->id,
            'attempts' => 0,
        ], $ttl);

        $smsSent = $this->dispatchSms($normalized, $code, $user);
        $exposeDebug = config('otp.expose_code_in_response') || (config('app.debug') && ! $smsSent);

        if (! $smsSent && ! $exposeDebug) {
            Cache::forget($this->otpCacheKey($normalized));

            throw ValidationException::withMessages([
                'phone' => [$this->smsFailureMessage()],
            ]);
        }

        Cache::put(
            $cooldownKey,
            time() + config('otp.resend_cooldown_seconds', 60),
            config('otp.resend_cooldown_seconds', 60),
        );

        $payload = [
            'message' => $smsSent
                ? 'کد تأیید ارسال شد.'
                : 'سرویس پیامک فعال نیست — از کد تست زیر برای ورود استفاده کنید.',
            'phone' => PhoneNormalizer::display($normalized),
            'expires_in' => $ttl,
            'sms_sent' => $smsSent,
        ];

        if ($exposeDebug) {
            $payload['debug_code'] = $code;
        }

        return $payload;
    }

    public function verify(string $phone, string $code): User
    {
        $normalized = PhoneNormalizer::normalize($phone);
        $code = PhoneNormalizer::normalizeOtpCode($code);

        if (! $normalized) {
            throw ValidationException::withMessages([
                'phone' => ['شماره موبایل معتبر نیست.'],
            ]);
        }

        $expectedLength = config('otp.length', 6);

        if (strlen($code) !== $expectedLength) {
            throw ValidationException::withMessages([
                'code' => ['کد تأیید باید ۶ رقم باشد.'],
            ]);
        }

        $stored = Cache::get($this->otpCacheKey($normalized));

        if (! $stored) {
            throw ValidationException::withMessages([
                'code' => ['کد منقضی شده است. لطفاً دوباره درخواست دهید.'],
            ]);
        }

        $maxAttempts = config('otp.max_attempts', 5);
        $attempts = (int) ($stored['attempts'] ?? 0);

        if ($attempts >= $maxAttempts) {
            Cache::forget($this->otpCacheKey($normalized));

            throw ValidationException::withMessages([
                'code' => ['تعداد تلاش‌ها بیش از حد مجاز است. کد جدید درخواست کنید.'],
            ]);
        }

        if (! hash_equals((string) $stored['code'], $code)) {
            $stored['attempts'] = $attempts + 1;
            Cache::put($this->otpCacheKey($normalized), $stored, config('otp.ttl_seconds', 300));

            throw ValidationException::withMessages([
                'code' => ['کد وارد شده نادرست است.'],
            ]);
        }

        Cache::forget($this->otpCacheKey($normalized));

        $user = User::find($stored['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['کاربر یافت نشد.'],
            ]);
        }

        return $user;
    }

    protected function generateCode(): string
    {
        $length = config('otp.length', 6);
        $max = (10 ** $length) - 1;
        $code = (string) random_int(0, $max);

        return str_pad($code, $length, '0', STR_PAD_LEFT);
    }

    protected function dispatchSms(string $phone, string $code, User $user): bool
    {
        $this->smsNotConfigured = false;
        $this->lastSmsError = null;

        if (! IppanelOtpService::isPlatformConfigured()) {
            $this->smsNotConfigured = true;

            Log::info('OTP SMS skipped (platform IPPanel not configured)', [
                'phone' => $phone,
            ]);

            return false;
        }

        try {
            return $this->ippanelOtp->send($phone, $code, $user);
        } catch (\Throwable $e) {
            $this->lastSmsError = $e->getMessage();

            Log::warning('OTP SMS dispatch failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function smsFailureMessage(): string
    {
        if ($this->smsNotConfigured) {
            return 'سرویس پیامک پیکربندی نشده است. IPPANEL_AGENCY_API_KEY و IPPANEL_OTP_FROM_NUMBER را در .env تنظیم کنید و سپس php artisan config:clear را روی سرور اجرا کنید.';
        }

        if ($this->lastSmsError) {
            return 'ارسال پیامک ناموفق بود: '.$this->lastSmsError;
        }

        return 'ارسال پیامک ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.';
    }

    protected function infrastructureFailureMessage(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $message = $e->getMessage();

            if (str_contains($message, 'cache') || str_contains($message, 'cache_locks')) {
                return 'خطای پایگاه داده در cache. روی سرور php artisan migrate --force و سپس php artisan config:clear را اجرا کنید.';
            }

            if (str_contains($message, 'Unknown database') || str_contains($message, 'Access denied')) {
                return 'اتصال به پایگاه داده برقرار نیست. تنظیمات DB_* در .env سرور را بررسی کنید.';
            }

            if (str_contains($message, 'users') && str_contains($message, 'Unknown column')) {
                return 'ساختار پایگاه داده به‌روز نیست. روی سرور php artisan migrate --force را اجرا کنید.';
            }
        }

        if (config('app.debug')) {
            return 'خطای داخلی در ارسال کد تأیید: '.$e->getMessage();
        }

        return 'خطای داخلی در ارسال کد تأیید. لطفاً با پشتیبانی تماس بگیرید.';
    }

    protected function otpCacheKey(string $phone): string
    {
        return "auth_otp:{$phone}";
    }

    protected function cooldownCacheKey(string $phone): string
    {
        return "auth_otp_cooldown:{$phone}";
    }
}
