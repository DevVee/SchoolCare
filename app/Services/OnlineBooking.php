<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentTimeSlot;
use App\Models\Patient;
use App\Models\SpecialistVisit;
use App\Support\ClinicHours;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rules of the public appointment request form (/request-appointment). Every
 * rule comes from Admin > Settings > Appointments (plus the lists under Clinic
 * and Academic, and the slots under Administration > Appointment Slots):
 *
 *  - which questions the form asks: required, optional or hidden;
 *  - which dates it offers: open clinic days from today up to "how far ahead"
 *    (never past the booking window), weekends only when allowed, never a
 *    closed date, and, when turned on, only a specialist's visit days;
 *  - which times it offers: active slots offered on that weekday that start
 *    inside the clinic hours (and inside the specialist's visit hours), after
 *    the minimum notice, with a place left: the slot capacity, the share of it
 *    online requests may take, and the daily limit.
 *
 * AppointmentBooking checks the capacity again inside the booking transaction.
 */
class OnlineBooking
{
    public const MODES = ['required', 'optional', 'hidden'];

    /** Form question => setting that says whether it is required, optional or hidden. */
    public const FIELDS = [
        'category'   => 'public_booking_field_category',
        'school'     => 'public_booking_field_school',
        'student_id' => 'public_booking_field_student_id',
        'email'      => 'public_booking_field_email',
        'provider'   => 'public_booking_field_provider',
        'details'    => 'public_booking_field_details',
    ];

    /** @var Collection<int, AppointmentTimeSlot>|null */
    private ?Collection $activeSlots = null;

    public function enabled(): bool
    {
        return (bool) settings('public_booking_enabled', false);
    }

    // ─── Questions ───────────────────────────────────────────────────────────

    public function mode(string $field): string
    {
        $key  = self::FIELDS[$field] ?? null;
        $mode = $key ? (string) settings($key, 'optional') : 'optional';

        return in_array($mode, self::MODES, true) ? $mode : 'optional';
    }

    public function shows(string $field): bool
    {
        if ($field === 'provider' && $this->providers() === []) {
            return false;
        }
        if ($field === 'category' && $this->categories() === []) {
            return false;
        }

        return $this->mode($field) !== 'hidden';
    }

    public function requires(string $field): bool
    {
        return $this->shows($field) && $this->mode($field) === 'required';
    }

    /** @return list<string> reasons offered (empty: the person types one) */
    public function purposes(): array
    {
        return settings()->list('appointment_purposes');
    }

    /** "Other", "Other (please specify)", ... ask the person to type their own reason. */
    public static function isOther(?string $purpose): bool
    {
        return (bool) preg_match('/^other\b/i', trim((string) $purpose));
    }

    /** @return list<string> */
    public function providers(): array
    {
        return settings()->list('appointment_providers');
    }

    /** @return array<string,string> patient category value => label */
    public function categories(): array
    {
        return Patient::categoryLabels();
    }

    public function consentRequired(): bool
    {
        return (bool) settings('public_booking_consent_required', true);
    }

    // ─── Dates ───────────────────────────────────────────────────────────────

    /** The last date the form offers: "how far ahead", never past the booking window. */
    public function lastDate(): Carbon
    {
        $days = max(1, (int) settings('public_booking_days_ahead', 30));
        $cap  = (int) settings('booking_max_days_ahead', 0);
        if ($cap > 0) {
            $days = min($days, $cap);
        }

        return today()->addDays($days);
    }

    public function minNoticeHours(): int
    {
        return max(0, (int) settings('public_booking_min_notice_hours', 0));
    }

    /** @return array<string,string> 'Y-m-d' => note, from the Closed dates list (lines without a valid date are ignored) */
    public function closedDates(): array
    {
        $out = [];
        foreach (settings()->list('public_booking_closed_dates') as $line) {
            if (! preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})(.*)$/u', $line, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                continue;
            }
            $out["{$m[1]}-{$m[2]}-{$m[3]}"] = trim($m[4], " \t-:,");
        }

        return $out;
    }

    /** @return list<string> providers whose requests are limited to their visit days */
    public function specialistProviders(): array
    {
        if (! settings('public_booking_specialist_days_only', false)) {
            return [];
        }
        $types = array_map('mb_strtolower', SpecialistVisit::types());

        return array_values(array_filter($this->providers(), fn ($p) => in_array(mb_strtolower($p), $types, true)));
    }

    public function isSpecialist(?string $provider): bool
    {
        return $provider !== null && $provider !== '' && in_array($provider, $this->specialistProviders(), true);
    }

    /** The scheduled visit of a specialist on a date, if any. */
    public function visitFor(CarbonInterface|string $date, ?string $provider): ?SpecialistVisit
    {
        if ($provider === null || $provider === '') {
            return null;
        }

        return SpecialistVisit::scheduled()
            ->whereDate('visit_date', $this->day($date)->toDateString())
            ->where('type', $provider)
            ->orderBy('start_time')
            ->first();
    }

    /** Why a date cannot be requested, or null when it can (its times are checked separately). */
    public function dateProblem(CarbonInterface|string $date, ?string $provider = null): ?string
    {
        $day = $this->day($date);

        if ($day->lt(today())) {
            return 'Choose today or a later date.';
        }
        if ($day->gt($last = $this->lastDate())) {
            return 'Requests can be made up to '.$last->format('F j, Y').'. Choose an earlier date.';
        }
        if (! ClinicHours::isOpenOn($day)) {
            return 'The clinic is closed on '.$day->format('l').'s. Choose another date.';
        }
        if (! settings('allow_weekend_booking', true) && $day->isWeekend()) {
            return 'Appointments cannot be requested on weekends. Choose a weekday.';
        }
        $closed = $this->closedDates();
        if (array_key_exists($day->toDateString(), $closed)) {
            $note = $closed[$day->toDateString()];

            return 'The clinic is closed on '.$day->format('F j').($note !== '' ? " ({$note})" : '').'. Choose another date.';
        }
        if ($this->isSpecialist($provider) && ! $this->visitFor($day, $provider)) {
            return 'The '.mb_strtolower((string) $provider).' is not scheduled to visit on '.$day->format('F j').'. Choose one of the dates in the list.';
        }

        return null;
    }

    /** True when the daily appointment limit is already reached on a date. */
    public function dayIsFull(CarbonInterface|string $date): bool
    {
        $max = (int) settings('max_daily_appointments', 50);
        if ($max <= 0) {
            return false;
        }

        return Appointment::whereDate('appointment_date', $this->day($date)->toDateString())
            ->whereIn('status', AppointmentBooking::HOLDING)
            ->count() >= $max;
    }

    /**
     * Open clinic days from today to the last date, for the date dropdown.
     * `open` counts the times still open for anyone; `visits` lists the
     * specialist types scheduled that day; `closed` is the note of a closed date.
     *
     * @return list<array{date:string,label:string,open:int,closed:?string,visits:list<string>}>
     */
    public function dates(): array
    {
        $from   = today();
        $to     = $this->lastDate();
        $counts = $this->counts($from, $to);
        $closed = $this->closedDates();
        $visits = $this->visitTypesBetween($from, $to);
        $weekendsOk = (bool) settings('allow_weekend_booking', true);

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (! ClinicHours::isOpenOn($d) || (! $weekendsOk && $d->isWeekend())) {
                continue;
            }
            $key = $d->toDateString();
            $out[] = [
                'date'   => $key,
                'label'  => $this->dateLabel($d),
                'open'   => array_key_exists($key, $closed) ? 0
                    : $this->evaluate($d->copy(), $counts[$key] ?? [], null)->where('available', true)->count(),
                'closed' => array_key_exists($key, $closed) ? $closed[$key] : null,
                'visits' => $visits[$key] ?? [],
            ];
        }

        return $out;
    }

    /** "Today, Wednesday, September 30" / "Tomorrow, ..." / "Friday, October 2". */
    public function dateLabel(CarbonInterface $date): string
    {
        $label = $date->format('l, F j');

        return match (true) {
            $date->isToday()    => 'Today, '.$label,
            $date->isTomorrow() => 'Tomorrow, '.$label,
            default             => $label,
        };
    }

    // ─── Times ───────────────────────────────────────────────────────────────

    /**
     * The times the form offers on a date (empty when the date cannot be requested).
     * state: open | full | past (already started) | soon (inside the minimum notice)
     *
     * @return Collection<int, array{time:string,label:string,remaining:int,available:bool,state:string}>
     */
    public function slots(CarbonInterface|string $date, ?string $provider = null): Collection
    {
        $day = $this->day($date);
        if ($this->dateProblem($day, $provider) !== null) {
            return collect();
        }

        $counts = $this->counts($day, $day)[$day->toDateString()] ?? [];

        return $this->evaluate($day, $counts, $this->isSpecialist($provider) ? $this->visitFor($day, $provider) : null);
    }

    /** One offered time on a date, or null when the form does not offer it. */
    public function slot(CarbonInterface|string $date, string $time, ?string $provider = null): ?array
    {
        $time = app(AppointmentBooking::class)->normalizeTime($time);

        return $this->slots($date, $provider)->firstWhere('time', $time);
    }

    /**
     * @param  array{total?:int,times?:array<string,array{all:int,online:int}>}  $counts
     */
    private function evaluate(Carbon $day, array $counts, ?SpecialistVisit $visit): Collection
    {
        $limit    = max(0, (int) settings('public_booking_slot_limit', 0));
        $max      = (int) settings('max_daily_appointments', 50);
        $dayFull  = $max > 0 && ($counts['total'] ?? 0) >= $max;
        $now      = now();
        $earliest = $now->copy()->addHours($this->minNoticeHours());
        $booking  = app(AppointmentBooking::class);

        // A specialist's visit: only its hours, and nothing once its capacity is used up.
        $visitFrom = $visit?->start_time ? substr((string) $visit->start_time, 0, 5) : null;
        $visitTo   = $visit?->end_time ? substr((string) $visit->end_time, 0, 5) : null;
        $visitFull = false;
        if ($visit && $visit->capacity !== null) {
            $visitFull = $visit->appointments()->whereIn('status', AppointmentBooking::HOLDING)->count() >= $visit->capacity;
        }

        return $this->activeSlots()
            ->filter(fn (AppointmentTimeSlot $s) => $s->isOfferedOn($day) && ClinicHours::isWithinHours($day, (string) $s->slot_time))
            ->filter(function (AppointmentTimeSlot $s) use ($visitFrom, $visitTo) {
                $t = substr((string) $s->slot_time, 0, 5);

                return ($visitFrom === null || $t >= $visitFrom) && ($visitTo === null || $t < $visitTo);
            })
            ->map(function (AppointmentTimeSlot $s) use ($day, $counts, $limit, $dayFull, $visitFull, $now, $earliest, $booking) {
                $time = $booking->normalizeTime((string) $s->slot_time);
                $held = $counts['times'][$time] ?? ['all' => 0, 'online' => 0];

                $left = max(0, (int) $s->max_appointments - $held['all']);
                if ($limit > 0) {
                    $left = min($left, max(0, $limit - $held['online']));
                }
                if ($dayFull || $visitFull) {
                    $left = 0;
                }

                $start = Carbon::parse($day->toDateString().' '.$time);
                $state = match (true) {
                    $start->lte($now)     => 'past',
                    $start->lt($earliest) => 'soon',
                    $left > 0             => 'open',
                    default               => 'full',
                };

                return [
                    'time'      => $time,
                    'label'     => $s->display_label,
                    'remaining' => $left,
                    'available' => $state === 'open',
                    'state'     => $state,
                ];
            })
            ->values();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @return Collection<int, AppointmentTimeSlot> */
    private function activeSlots(): Collection
    {
        return $this->activeSlots ??= AppointmentTimeSlot::active()->get();
    }

    /**
     * Places held per date and time, all sources and online only, in one query.
     *
     * @return array<string, array{total:int, times:array<string,array{all:int,online:int}>}>
     */
    private function counts(Carbon $from, Carbon $to): array
    {
        $booking = app(AppointmentBooking::class);
        $rows = Appointment::query()
            ->whereDate('appointment_date', '>=', $from->toDateString())
            ->whereDate('appointment_date', '<=', $to->toDateString())
            ->whereIn('status', AppointmentBooking::HOLDING)
            ->selectRaw('appointment_date as d, appointment_time as t, source as s, count(*) as n')
            ->groupBy('appointment_date', 'appointment_time', 'source')
            ->toBase()
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->d, 0, 10);
            $time = $booking->normalizeTime((string) $row->t);
            $n    = (int) $row->n;

            $out[$date]['total'] = ($out[$date]['total'] ?? 0) + $n;
            $out[$date]['times'][$time]['all'] = ($out[$date]['times'][$time]['all'] ?? 0) + $n;
            $out[$date]['times'][$time]['online'] = ($out[$date]['times'][$time]['online'] ?? 0)
                + ($row->s === Appointment::SOURCE_ONLINE ? $n : 0);
        }

        return $out;
    }

    /** @return array<string, list<string>> 'Y-m-d' => specialist types scheduled that day */
    private function visitTypesBetween(Carbon $from, Carbon $to): array
    {
        $out = [];
        SpecialistVisit::scheduled()
            ->whereDate('visit_date', '>=', $from->toDateString())
            ->whereDate('visit_date', '<=', $to->toDateString())
            ->get(['type', 'visit_date'])
            ->each(function (SpecialistVisit $v) use (&$out) {
                $key = $v->visit_date->toDateString();
                if (! in_array($v->type, $out[$key] ?? [], true)) {
                    $out[$key][] = $v->type;
                }
            });

        return $out;
    }

    private function day(CarbonInterface|string $date): Carbon
    {
        return Carbon::parse($date instanceof CarbonInterface ? $date->toDateString() : $date)->startOfDay();
    }
}
