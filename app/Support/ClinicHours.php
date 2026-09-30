<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Weekly opening hours from settings('clinic_weekly_hours'):
 *   ['monday' => '07:30-17:00', 'saturday' => 'closed', ...]
 */
class ClinicHours
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** @return array<string, array{open:string,close:string}|null> day => hours or null when closed */
    public static function week(): array
    {
        $raw = settings()->options('clinic_weekly_hours');
        $out = [];

        foreach (self::DAYS as $day) {
            $out[$day] = self::parse($raw[$day] ?? null);
        }

        // Nothing configured at all: treat every day as open (legacy behaviour).
        if (array_filter($raw) === []) {
            return array_fill_keys(self::DAYS, ['open' => '00:00', 'close' => '23:59']);
        }

        return $out;
    }

    /** Hours for a date, or null when the clinic is closed that day. */
    public static function forDate(CarbonInterface|string $date): ?array
    {
        $day = strtolower(($date instanceof CarbonInterface ? $date : Carbon::parse($date))->englishDayOfWeek);

        return self::week()[$day] ?? null;
    }

    public static function isOpenOn(CarbonInterface|string $date): bool
    {
        return self::forDate($date) !== null;
    }

    /** True when a HH:MM[:SS] time falls inside the opening hours of the date. */
    public static function isWithinHours(CarbonInterface|string $date, string $time): bool
    {
        $hours = self::forDate($date);
        if (! $hours) {
            return false;
        }
        $t = substr($time, 0, 5);

        return $t >= $hours['open'] && $t < $hours['close'];
    }

    private static function parse(?string $value): ?array
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'closed') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})\s*(?:-|to)\s*(\d{1,2}):(\d{2})$/', $value, $m)) {
            return [
                'open'  => sprintf('%02d:%02d', $m[1], $m[2]),
                'close' => sprintf('%02d:%02d', $m[3], $m[4]),
            ];
        }

        return null;
    }
}
