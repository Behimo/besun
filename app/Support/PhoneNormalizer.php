<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function toLatinDigits(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $phone = self::toLatinDigits($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return $digits;
        }

        return null;
    }

    public static function display(?string $phone): string
    {
        $normalized = self::normalize($phone);

        return $normalized ?? ($phone ?? '');
    }

    public static function normalizeOtpCode(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return preg_replace('/\D+/', '', self::toLatinDigits($code)) ?? '';
    }

    public static function toE164(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return null;
        }

        return '+98'.substr($normalized, 1);
    }

    /**
     * فرمت گیرنده IPPanel (E.164): +98912xxxxxxx
     */
    public static function toIppanelRecipient(?string $phone): ?string
    {
        return self::toE164($phone);
    }
}
