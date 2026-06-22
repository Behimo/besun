<?php

namespace App\Infrastructure\Services\Sms;

use Illuminate\Support\Str;
use RuntimeException;

class IppanelAgencyService
{
    public function __construct(
        protected IppanelHttpClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{password: string, response: array}
     */
    public function createSubUser(array $payload): array
    {
        $password = $payload['password'] ?? $this->generatePassword();

        $body = [
            'user_name' => $payload['user_name'],
            'password' => $password,
            'national_code' => $payload['national_code'],
            'mobile_number' => $payload['mobile_number'],
            'birth_date' => $payload['birth_date'],
            'acl_id' => (int) ($payload['acl_id'] ?? config('sms.default_acl_id')),
            'name_family' => $payload['name_family'] ?? null,
            'company' => $payload['company'] ?? null,
            'email' => $payload['email'] ?? null,
            'description' => $payload['description'] ?? null,
        ];

        $response = $this->client->post('/api/user/create', array_filter($body, fn ($v) => $v !== null && $v !== ''));

        $user = $this->findUserByUsername($payload['user_name']);

        return [
            'password' => $password,
            'user_id' => $user['user_id'] ?? null,
            'username' => $payload['user_name'],
            'response' => $response,
        ];
    }

    public function showUser(int $userId): array
    {
        $response = $this->client->get('/api/user/show', ['user_id' => $userId]);

        return $response['data'] ?? [];
    }

    public function findUserByUsername(string $username): array
    {
        $response = $this->client->get('/api/user/list', [
            'page' => 1,
            'per_page' => 1,
            'username' => $username,
        ]);

        $users = $response['data'] ?? [];

        return $users[0] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUserPackages(): array
    {
        $response = $this->client->get('/api/acl/package/list', [
            'page' => 1,
            'per_page' => 200,
            'status' => 'active',
            'type' => 'user',
        ]);

        return $response['data'] ?? [];
    }

    public function assignPackageToUser(int $userId, int $packageAclId): array
    {
        return $this->client->post('/api/user/charge', [
            'user_id' => $userId,
            'acl_role_id' => $packageAclId,
        ]);
    }

    public function syncUserCredit(int $userId): float
    {
        $user = $this->showUser($userId);
        $credit = $user['credit']['credit'] ?? 0;

        return (float) $credit;
    }

    protected function generatePassword(): string
    {
        return Str::password(16, symbols: true);
    }
}
