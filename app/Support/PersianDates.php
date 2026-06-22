<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Morilog\Jalali\Jalalian;

class PersianDates
{
    public static function format(DateTimeInterface|string|null $date, string $format = 'Y/m/d H:i:s'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return Jalalian::fromDateTime($date)->format($format);
    }

    public static function short(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'Y/m/d');
    }

    public static function dateTime(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'Y/m/d H:i');
    }

    public static function parse(?string $value, bool $endOfDay = false): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = self::normalizeDigits(trim($value));

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $value, $m)) {
            $year = (int) $m[1];

            if ($year >= 1300 && $year < 1500) {
                try {
                    $hasTime = isset($m[4]) && $m[4] !== '';
                    $format = $hasTime
                        ? (isset($m[6]) ? 'Y/m/d H:i:s' : 'Y/m/d H:i')
                        : 'Y/m/d';

                    $jalali = Jalalian::fromFormat($format, $value);
                    $carbon = $jalali->toCarbon();

                    return $endOfDay && ! $hasTime ? $carbon->endOfDay() : $carbon;
                } catch (\Throwable) {
                    //
                }
            }
        }

        try {
            $carbon = Carbon::parse($value);

            return $endOfDay ? $carbon->endOfDay() : $carbon;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        return str_replace(
            array_merge($persian, $arabic),
            array_merge(range(0, 9), range(0, 9)),
            $value
        );
    }

    public static function toDateString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::parse($value)?->toDateString();
    }
}
