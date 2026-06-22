<?php

namespace App\Infrastructure\Services\Sms;

use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IppanelOtpService
{
    public function __construct(
        protected IppanelHttpClient $client,
        protected IppanelTenantClient $tenantClient,
    ) {}

    public function send(string $phone, string $code, ?User $user = null): bool
    {
        $recipient = PhoneNormalizer::toIppanelRecipient($phone);

        if (! $recipient) {
            throw new RuntimeException('شماره موبایل برای ارسال OTP معتبر نیست.');
        }

        $tenantAccount = $user ? $this->resolveTenantAccount($user) : null;

        if ($tenantAccount) {
            $tenantFromNumber = $tenantAccount->default_from_number ?: config('sms.otp_from_number');

            if (! $tenantFromNumber) {
                Log::warning('Tenant OTP sender number is not configured; falling back to platform sender.', [
                    'tenant_id' => $tenantAccount->tenant_id,
                    'user_id' => $user?->id,
                ]);
            } else {
                try {
                    $this->sendViaTenant($tenantAccount, $tenantFromNumber, $recipient, $code);

                    return true;
                } catch (\Throwable $e) {
                    Log::warning('Tenant OTP SMS dispatch failed; falling back to platform sender.', [
                        'tenant_id' => $tenantAccount->tenant_id,
                        'user_id' => $user?->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (! self::isPlatformConfigured()) {
            return false;
        }

        $this->sendViaPlatform($recipient, $code);

        return true;
    }

    public static function isPlatformConfigured(): bool
    {
        return filled(config('sms.agency_api_key'))
            && filled(config('sms.otp_from_number'));
    }

    protected function sendViaPlatform(string $recipient, string $code): void
    {
        $fromNumber = config('sms.otp_from_number');
        $patternCode = config('sms.otp_pattern_code');

        if ($patternCode) {
            $this->client->post('/api/send', [
                'sending_type' => 'pattern',
                'from_number' => $fromNumber,
                'code' => $patternCode,
                'recipients' => [$recipient],
                'params' => [
                    config('sms.otp_pattern_param', 'code') => $code,
                ],
            ]);

            return;
        }

        $message = str_replace('%code%', $code, config('sms.otp_message', 'کد تأیید: %code%'));

        $this->client->post('/api/send', [
            'sending_type' => 'webservice',
            'from_number' => $fromNumber,
            'message' => $message,
            'params' => [
                'recipients' => [$recipient],
            ],
        ]);
    }

    protected function sendViaTenant(TenantSmsAccount $account, string $fromNumber, string $recipient, string $code): void
    {
        $patternCode = config('sms.otp_pattern_code');

        if ($patternCode) {
            $this->tenantClient->sendPattern(
                $account,
                $fromNumber,
                $patternCode,
                $recipient,
                [config('sms.otp_pattern_param', 'code') => $code],
            );

            return;
        }

        $message = str_replace('%code%', $code, config('sms.otp_message', 'کد تأیید: %code%'));

        $this->tenantClient->sendWebservice(
            $account,
            $fromNumber,
            $message,
            [$recipient],
        );
    }

    protected function resolveTenantAccount(User $user): ?TenantSmsAccount
    {
        if ($user->current_tenant_id) {
            $currentAccount = $this->activeAccountQuery()
                ->where('tenant_id', $user->current_tenant_id)
                ->first();

            if ($currentAccount) {
                return $currentAccount;
            }
        }

        $tenantIds = $user->tenants()
            ->whereNull('tenant_user.left_at')
            ->pluck('tenants.id');

        if ($tenantIds->isEmpty()) {
            return null;
        }

        $accounts = $this->activeAccountQuery()
            ->whereIn('tenant_id', $tenantIds)
            ->get();

        return $accounts->count() === 1 ? $accounts->first() : null;
    }

    protected function activeAccountQuery()
    {
        return TenantSmsAccount::query()
            ->where('status', TenantSmsAccount::STATUS_ACTIVE)
            ->whereNotNull('ippanel_user_id')
            ->where(function ($query) {
                $query->whereNotNull('api_key_encrypted')
                    ->orWhereNotNull('password_encrypted');
            });
    }
}

