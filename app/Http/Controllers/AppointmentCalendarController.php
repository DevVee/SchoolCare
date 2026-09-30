<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\SpecialistVisit;
use App\Services\AppointmentBooking;
use App\Support\ClinicHours;
use App\Support\MonthGrid;
use Illuminate\Http\Request;

/**
 * Appointments calendar (SSCMS appointments/todays-appointments.php):
 * a month view with per-day status counts and specialist days, plus a
 * "today" board.
 */
class AppointmentCalendarController extends Controller
{
    public function month(Request $request)
    {
        $this->authorize('view-appointments');

        $month = MonthGrid::month($request->query('month'));
        [$from, $to] = MonthGrid::range($month);

        $rows = Appointment::query()
            ->whereDate('appointment_date', '>=', $from)
            ->whereDate('appointment_date', '<=', $to)
            ->selectRaw('appointment_date, status, count(*) as n')
            ->groupBy('appointment_date', 'status')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $key = substr((string) $row->getRawOriginal('appointment_date'), 0, 10);
            $counts[$key][$row->status] = ($counts[$key][$row->status] ?? 0) + (int) $row->n;
        }

        $visits = [];
        if ($request->user()->can('view-specialist-visits')) {
            SpecialistVisit::query()
                ->whereDate('visit_date', '>=', $from)
                ->whereDate('visit_date', '<=', $to)
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time')
                ->get()
                ->each(function ($v) use (&$visits) {
                    $visits[$v->visit_date->toDateString()][] = $v;
                });
        }

        return view('appointments.calendar', [
            'month'        => $month,
            'weeks'        => MonthGrid::weeks($month),
            'counts'       => $counts,
            'visits'       => $visits,
            'statusLabels' => Appointment::statusLabels(),
            'closedDays'   => array_keys(array_filter(ClinicHours::week(), fn ($h) => $h === null)),
            'monthTotal'   => array_sum(array_map(fn ($day) => array_sum($day), array_filter(
                $counts,
                fn ($k) => str_starts_with($k, $month->format('Y-m')),
                ARRAY_FILTER_USE_KEY
            ))),
        ]);
    }

    public function today(Request $request, AppointmentBooking $booking)
    {
        $this->authorize('view-appointments');

        $date = today();
        if ($request->filled('date') && strtotime((string) $request->query('date'))) {
            $date = \Carbon\Carbon::parse((string) $request->query('date'))->startOfDay();
        }

        $appointments = Appointment::with('patient', 'specialistVisit')
            ->whereDate('appointment_date', $date->toDateString())
            ->orderBy('appointment_time')
            ->get();

        return view('appointments.today', [
            'date'         => $date,
            'appointments' => $appointments,
            'byTime'       => $appointments->groupBy('appointment_time'),
            'slots'        => $booking->slotsForDate($date->toDateString()),
            'visits'       => $request->user()->can('view-specialist-visits')
                ? SpecialistVisit::whereDate('visit_date', $date->toDateString())->orderBy('start_time')->get()
                : collect(),
            'statusLabels' => Appointment::statusLabels(),
            'hours'        => ClinicHours::forDate($date),
        ]);
    }
}
