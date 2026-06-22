<?php

namespace App\Infrastructure\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IppanelHttpClient
{
    public function agency(): PendingRequest
    {
        return $this->baseRequest(config('sms.agency_api_key'));
    }

    public function withToken(?string $token): PendingRequest
    {
        return $this->baseRequest($token);
    }

    public function get(string $path, array $query = [], ?string $token = null): array
    {
        $request = $token ? $this->withToken($token) : $this->agency();

        return $this->parseResponse($request->get($this->url($path), $query));
    }

    public function post(string $path, array $payload = [], ?string $token = null): array
    {
        $request = $token ? $this->withToken($token) : $this->agency();

        return $this->parseResponse($request->post($this->url($path), $payload));
    }

    public function postMultipart(string $path, array $payload = [], ?string $token = null): array
    {
        $request = $token ? $this->withToken($token) : $this->agency();
        $http = $request->asMultipart();

        $multipart = [];
        foreach ($payload as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => (string) $value];
        }

        return $this->parseResponse($http->post($this->url($path), $multipart));
    }

    protected function baseRequest(?string $authorization): PendingRequest
    {
        if (! $authorization) {
            throw new RuntimeException('کلید API پیامک تنظیم نشده است.');
        }

        return Http::baseUrl(config('sms.base_url'))
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $this->formatAuthorization($authorization),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->retry(2, 300, function ($exception) {
                return $exception instanceof ConnectionException;
            }, throw: false);
    }

    protected function formatAuthorization(string $authorization): string
    {
        $value = trim($authorization);

        // Edge API (edge.ippanel.com/v1): raw api key or session token in Authorization.
        // See ippanelcom/python-sdk — NOT the legacy rest.ippanel.com "AccessKey ..." scheme.
        return preg_replace('/^(AccessKey|Bearer)\s+/i', '', $value) ?: $value;
    }

    protected function url(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    protected function parseResponse(Response $response): array
    {
        $body = $response->json() ?? [];
        $meta = $body['meta'] ?? [];
        $status = $meta['status'] ?? $response->successful();

        if (! $response->successful() || $status === false) {
            $message = $meta['message'] ?? $response->body();
            $errors = $meta['errors'] ?? ($body['errors'] ?? []);

            Log::warning('IPPanel API error', [
                'status' => $response->status(),
                'message' => $message,
                'errors' => $errors,
            ]);

            throw new RuntimeException(is_string($message) ? $message : 'خطا در ارتباط با سرویس پیامک.');
        }

        return $body;
    }
}
