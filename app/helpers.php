<?php

use App\Support\PersianDates;
use Carbon\CarbonInterface;
use Morilog\Jalali\Jalalian;

if (! function_exists('persianDate')) {
    function persianDate($date, string $format = 'Y/m/d H:i:s'): string
    {
        return PersianDates::format($date, $format);
    }
}

if (! function_exists('persianDateShort')) {
    function persianDateShort($date): string
    {
        return PersianDates::short($date);
    }
}

if (! function_exists('jdate')) {
    function jdate($date = null): ?Jalalian
    {
        if ($date === null) {
            return Jalalian::now();
        }

        if ($date instanceof CarbonInterface || $date instanceof \DateTimeInterface) {
            return Jalalian::fromDateTime($date);
        }

        $parsed = PersianDates::parse((string) $date);

        return $parsed ? Jalalian::fromDateTime($parsed) : null;
    }
}
