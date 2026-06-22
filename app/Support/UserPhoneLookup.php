<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class UserPhoneLookup
{
    public static function normalizeOrFail(string $phone): string
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if (! $normalized) {
            throw new RuntimeException('شماره موبایل نامعتبر است. لطفاً شماره کامل ۱۱ رقمی وارد کنید (مثال: ۰۹۱۲۳۴۵۶۷۸۹)');
        }

        return $normalized;
    }

    public static function findByPhone(string $phone): ?User
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if (! $normalized) {
            return null;
        }

        return self::queryByNormalizedPhone($normalized)->first();
    }

    public static function queryByNormalizedPhone(string $normalized): Builder
    {
        return User::query()->where(function ($query) use ($normalized) {
            $query->where('phone', $normalized)
                ->orWhere('phone', ltrim($normalized, '0'))
                ->orWhere('phone', '+98'.substr($normalized, 1))
                ->orWhere('phone', '98'.substr($normalized, 1));
        });
    }

    public static function phonesMatch(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return false;
        }

        $na = PhoneNormalizer::normalize($a);
        $nb = PhoneNormalizer::normalize($b);

        return $na !== null && $na === $nb;
    }
}
