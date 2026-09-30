<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Date / time display using Admin → Settings → General (date_format, time_format).
 */
class DisplayFormat
{
    public static function date(CarbonInterface|string|null $value, string $empty = ''): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        try {
            $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return (string) $value;
        }

        return $date->format((string) (settings('date_format') ?: 'M d, Y'));
    }

    public static function time(CarbonInterface|string|null $value, string $empty = ''): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        try {
            $time = $value instanceof CarbonInterface ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return (string) $value;
        }

        return $time->format((string) (settings('time_format') ?: 'h:i A'));
    }

    /** "08:00 AM to 09:00 AM" (no en dash; see ui_principles). */
    public static function timeRange(?string $start, ?string $end): string
    {
        $s = self::time($start);
        $e = self::time($end);

        return $e !== '' ? "{$s} to {$e}" : $s;
    }

    public static function perPage(int $fallback = 20): int
    {
        $n = (int) settings('records_per_page', $fallback);

        return $n >= 5 && $n <= 200 ? $n : $fallback;
    }
}
