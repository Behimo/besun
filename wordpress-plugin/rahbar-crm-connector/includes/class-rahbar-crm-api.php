<?php

if (! defined('ABSPATH')) {
    exit;
}

class Rahbar_Crm_Api
{
    public function get_settings(): array
    {
        return [
            'crm_url' => rtrim((string) get_option('rahbar_crm_crm_url', ''), '/'),
            'bridge_token' => (string) get_option('rahbar_crm_bridge_token', ''),
            'bridge_secret' => (string) get_option('rahbar_crm_bridge_secret', ''),
        ];
    }

    public function is_configured(): bool
    {
        $settings = $this->get_settings();

        return $settings['crm_url'] !== ''
            && $settings['bridge_token'] !== ''
            && $settings['bridge_secret'] !== '';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, code: int, data: array<string, mixed>|null, error: string|null}
     */
    public function post(string $endpoint, array $body): array
    {
        $settings = $this->get_settings();

        if (! $this->is_configured()) {
            return ['ok' => false, 'code' => 0, 'data' => null, 'error' => 'تنظیمات CRM کامل نیست.'];
        }

        $json = wp_json_encode($body);
        $signature = base64_encode(hash_hmac('sha256', $json, $settings['bridge_secret'], true));
        $url = $settings['crm_url'].'/api/v1/integrations/woocommerce/bridge/'.$settings['bridge_token'].'/'.$endpoint;

        $response = wp_remote_post($url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Rahbar-Signature' => $signature,
                'User-Agent' => 'RahbarCRM-WordPress/'.RAHBAR_CRM_CONNECTOR_VERSION,
            ],
            'body' => $json,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'code' => 0, 'data' => null, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'code' => $code, 'data' => is_array($data) ? $data : [], 'error' => null];
        }

        $message = is_array($data) && ! empty($data['message']) ? (string) $data['message'] : $raw;

        return ['ok' => false, 'code' => $code, 'data' => is_array($data) ? $data : null, 'error' => $message];
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>|null, error: string|null}
     */
    public function fetch_commands(): array
    {
        $result = $this->post('commands', []);

        if ($result['ok']) {
            return ['ok' => true, 'data' => $result['data'], 'error' => null];
        }

        return ['ok' => false, 'data' => null, 'error' => $result['error']];
    }

    /**
     * @param  array<string, bool>  $payload
     */
    public function ack_commands(array $payload): void
    {
        $this->post('commands/ack', $payload);
    }
}
