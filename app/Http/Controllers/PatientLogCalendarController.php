<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientLog;
use App\Support\MonthGrid;
use Illuminate\Http\Request;

/**
 * Clinic visits calendar (SSCMS calendar/calendar.php "Reports Calendar"):
 * number of logged visits per day, optionally for one patient category.
 * Days with more than 5 visits are highlighted, as in SSCMS.
 */
class PatientLogCalendarController extends Controller
{
    public const BUSY_DAY = 5;

    public function month(Request $request)
    {
        $this->authorize('view-patient-logs');

        $month      = MonthGrid::month($request->query('month'));
        [$from, $to] = MonthGrid::range($month);
        $categories = Patient::categoryLabels();
        $category   = array_key_exists((string) $request->query('category'), $categories) ? (string) $request->query('category') : '';

        $rows = PatientLog::query()
            ->whereDate('log_date', '>=', $from)
            ->whereDate('log_date', '<=', $to)
            ->when($category !== '', fn ($q) => $q->whereHas('patient', fn ($p) => $p->withTrashed()->where('category', $category)))
            ->selectRaw('log_date, count(*) as n')
            ->groupBy('log_date')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $key = substr((string) $row->getRawOriginal('log_date'), 0, 10);
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->n;
        }

        $inMonth = array_filter($counts, fn ($k) => str_starts_with($k, $month->format('Y-m')), ARRAY_FILTER_USE_KEY);

        return view('patient-logs.calendar', [
            'month'      => $month,
            'weeks'      => MonthGrid::weeks($month),
            'counts'     => $counts,
            'categories' => $categories,
            'category'   => $category,
            'monthTotal' => array_sum($inMonth),
            'busiest'    => $inMonth ? array_search(max($inMonth), $inMonth, true) : null,
            'busyDay'    => self::BUSY_DAY,
        ]);
    }
}
