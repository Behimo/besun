<?php

namespace App\Infrastructure\Services\Sms;

use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IppanelTenantClient
{
    public function __construct(
        protected IppanelHttpClient $client,
    ) {}

    public function getCredit(TenantSmsAccount $account): float
    {
        $token = $this->resolveAuth($account);
        $response = $this->client->get('/api/payment/credit/mine', [], $token);

        return (float) ($response['data']['credit'] ?? 0);
    }

    /**
     * @return array<int, string>
     */
    public function listNumbers(TenantSmsAccount $account): array
    {
        $token = $this->resolveAuth($account);

        try {
            $response = $this->client->get('/api/numbers', ['page' => 1, 'per_page' => 50], $token);
            $rows = $response['data'] ?? [];

            return collect($rows)
                ->map(fn ($row) => $row['number'] ?? $row['from_number'] ?? null)
                ->filter()
                ->values()
                ->all();
        } catch (RuntimeException) {
            return $account->default_from_number ? [$account->default_from_number] : [];
        }
    }

    /**
     * @param  array<int, string>  $recipients
     */
    public function sendWebservice(
        TenantSmsAccount $account,
        string $fromNumber,
        string $message,
        array $recipients,
        ?string $sendTime = null,
    ): array {
        $token = $this->resolveAuth($account);

        $payload = [
            'sending_type' => 'webservice',
            'from_number' => $fromNumber,
            'message' => $message,
            'params' => [
                'recipients' => $this->normalizeRecipients($recipients),
            ],
        ];

        if ($sendTime) {
            $payload['send_time'] = $sendTime;
        }

        return $this->client->post('/api/send', $payload, $token);
    }

    public function sendPattern(
        TenantSmsAccount $account,
        string $fromNumber,
        string $patternCode,
        string $recipient,
        array $params,
    ): array {
        $token = $this->resolveAuth($account);

        return $this->client->post('/api/send', [
            'sending_type' => 'pattern',
            'from_number' => $fromNumber,
            'code' => $patternCode,
            'recipients' => [$this->normalizeRecipient($recipient)],
            'params' => $params,
        ], $token);
    }

    protected function normalizeRecipient(string $phone): string
    {
        return PhoneNormalizer::toIppanelRecipient($phone) ?? ('+'.ltrim($phone, '+'));
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<int, string>
     */
    protected function normalizeRecipients(array $recipients): array
    {
        return array_values(array_map(fn (string $phone) => $this->normalizeRecipient($phone), $recipients));
    }

    protected function resolveAuth(TenantSmsAccount $account): string
    {
        if ($account->api_key_encrypted) {
            return Crypt::decryptString($account->api_key_encrypted);
        }

        if (! $account->ippanel_username || ! $account->password_encrypted) {
            throw new RuntimeException('اعتبارنامه پنل پیامک این مجموعه تنظیم نشده است.');
        }

        $cacheKey = "ippanel_token:{$account->tenant_id}";

        return Cache::remember($cacheKey, now()->addHours(9), function () use ($account) {
            $password = Crypt::decryptString($account->password_encrypted);

            $response = Http::acceptJson()->timeout(30)->post(config('sms.base_url').'/api/acl/auth/login', [
                'username' => $account->ippanel_username,
                'password' => $password,
            ])->json() ?? [];

            $method = $response['data']['method'] ?? null;
            $token = $response['data']['token'] ?? null;

            if ($method !== 'login' || ! $token) {
                throw new RuntimeException('ورود به پنل پیامک زیرمجموعه ممکن نیست. لطفاً با پشتیبانی تماس بگیرید.');
            }

            return $token;
        });
    }
}
