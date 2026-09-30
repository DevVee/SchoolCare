<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Server-rendered month calendar (Monday first). Used by the appointments
 * calendar and the clinic visits calendar.
 */
class MonthGrid
{
    /** Parse ?month=YYYY-MM (falls back to the current month). */
    public static function month(?string $value): CarbonImmutable
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            try {
                $m = CarbonImmutable::createFromFormat('!Y-m', $value);
                if ($m && $m->year >= 2000 && $m->year <= 2100) {
                    return $m->startOfMonth();
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    /**
     * Weeks of days: [[['date' => CarbonImmutable, 'key' => 'Y-m-d', 'inMonth' => bool, 'isToday' => bool], ...7], ...].
     */
    public static function weeks(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end   = $month->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $today = CarbonImmutable::today()->toDateString();

        $weeks = [];
        $week  = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $week[] = [
                'date'    => $day,
                'key'     => $day->toDateString(),
                'inMonth' => $day->month === $month->month,
                'isToday' => $day->toDateString() === $today,
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /** First and last date shown in the grid (whole weeks). */
    public static function range(CarbonImmutable $month): array
    {
        return [
            $month->startOfMonth()->startOfWeek(Carbon::MONDAY)->toDateString(),
            $month->endOfMonth()->endOfWeek(Carbon::SUNDAY)->toDateString(),
        ];
    }
}
