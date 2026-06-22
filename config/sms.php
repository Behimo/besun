<?php

return [
    'base_url' => rtrim(env('IPPANEL_BASE_URL', 'https://edge.ippanel.com/v1'), '/'),

    'agency_api_key' => (static function (): ?string {
        $value = env('IPPANEL_AGENCY_API_KEY');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^IPPANEL_AGENCY_API_KEY=(.+)$/i', $value, $matches)) {
            $value = trim($matches[1]);
        }

        return $value !== '' ? $value : null;
    })(),

    'default_acl_id' => env('IPPANEL_DEFAULT_ACL_ID') ? (int) env('IPPANEL_DEFAULT_ACL_ID') : null,

    'platform_admin_user_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('SMS_PLATFORM_ADMIN_IDS', '')),
    ))),

    'otp_from_number' => env('IPPANEL_OTP_FROM_NUMBER'),

    /*
    | پترن OTP پلتفرم — طبق مستندات IPPanel برای OTP باید از sending_type=pattern استفاده شود.
    | کد پترن را از پنل IPPanel > پترن‌ها کپی کنید (متغیر پیش‌فرض: code).
    */
    'otp_pattern_code' => env('IPPANEL_OTP_PATTERN_CODE'),

    'otp_pattern_param' => env('IPPANEL_OTP_PATTERN_PARAM', 'code'),

    'otp_message' => env('IPPANEL_OTP_MESSAGE', 'کد تأیید رهبر CRM: %code%'),

    /*
    | true = بعد از ثبت فرم هویت در تنظیمات مجموعه، زیرکاربر IPPanel بدون تأیید دستی ساخته شود.
    */
    'auto_approve_sms_requests' => filter_var(env('IPPANEL_AUTO_APPROVE_SMS_REQUESTS', true), FILTER_VALIDATE_BOOL),

    'bulk_batch_size' => (int) env('SMS_BULK_BATCH_SIZE', 100),

    'credit_cache_ttl_seconds' => (int) env('SMS_CREDIT_CACHE_TTL', 300),
];
