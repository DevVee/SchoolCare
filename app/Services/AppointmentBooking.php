<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentTimeSlot;
use App\Models\SpecialistVisit;
use App\Support\ClinicHours;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Capacity-safe booking.
 *
 * The capacity check and the insert run in one transaction, and the counts are
 * taken again inside it after locking the slot row, so two people booking the
 * last place at the same moment cannot both succeed:
 *   - MySQL / PostgreSQL: SELECT ... FOR UPDATE on the slot row serialises
 *     bookings of the same slot;
 *   - SQLite: writes are serialised by the database (one writer at a time),
 *     and the re-count runs inside the same transaction as the insert.
 */
class AppointmentBooking
{
    /** Statuses that hold a place in a slot. */
    public const HOLDING = ['pending', 'approved'];

    // ─── Availability ────────────────────────────────────────────────────────

    public function slot(string $time): ?AppointmentTimeSlot
    {
        return AppointmentTimeSlot::where('slot_time', $this->normalizeTime($time))->where('is_active', true)->first();
    }

    /** Places left in a slot on a date (0 = full or not offered). */
    public function remaining(string $date, string $time, ?int $excludeId = null): int
    {
        $slot = $this->slot($time);
        if (! $slot || ! $slot->isOfferedOn($date)) {
            return 0;
        }

        return max(0, $slot->max_appointments - $this->bookedInSlot($date, $slot->slot_time, $excludeId));
    }

    /**
     * Every active slot for a date with its remaining capacity.
     *
     * @return Collection<int, array{slot: AppointmentTimeSlot, booked: int, remaining: int, available: bool, past: bool}>
     */
    public function slotsForDate(string $date): Collection
    {
        $day    = Carbon::parse($date)->startOfDay();
        $booked = Appointment::whereDate('appointment_date', $day->toDateString())
            ->whereIn('status', self::HOLDING)
            ->selectRaw('appointment_time, count(*) as n')
            ->groupBy('appointment_time')
            ->pluck('n', 'appointment_time');

        return AppointmentTimeSlot::active()->get()
            ->filter(fn (AppointmentTimeSlot $s) => $s->isOfferedOn($day))
            ->map(function (AppointmentTimeSlot $slot) use ($booked, $day) {
                $n    = (int) ($booked[$slot->slot_time] ?? 0);
                $left = max(0, $slot->max_appointments - $n);
                $past = $day->isToday() && Carbon::parse($day->toDateString().' '.$slot->slot_time)->isPast();

                return [
                    'slot'      => $slot,
                    'booked'    => $n,
                    'remaining' => $left,
                    'available' => $left > 0 && ! $past,
                    'past'      => $past,
                ];
            })
            ->values();
    }

    // ─── Writes ──────────────────────────────────────────────────────────────

    /**
     * Create an appointment if the slot (and optional specialist visit) still
     * has room. Throws a ValidationException on appointment_time otherwise.
     */
    public function book(array $data, bool $enforceClinicHours = false): Appointment
    {
        return DB::transaction(function () use ($data, $enforceClinicHours) {
            $this->guard($data, null, $enforceClinicHours);

            return Appointment::create($data);
        });
    }

    /** Update an appointment; capacity is re-checked when the slot or date changes. */
    public function reschedule(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data) {
            $moving = ($data['appointment_date'] ?? null) !== $appointment->appointment_date?->toDateString()
                || $this->normalizeTime((string) ($data['appointment_time'] ?? '')) !== $this->normalizeTime((string) $appointment->appointment_time)
                || (int) ($data['specialist_visit_id'] ?? 0) !== (int) $appointment->specialist_visit_id;

            if ($moving) {
                $this->guard($data, $appointment->id, false);
            }

            $appointment->update($data);

            return $appointment;
        });
    }

    /**
     * Lock the slot row, then re-count everything that limits the booking.
     *
     * @throws ValidationException
     */
    private function guard(array $data, ?int $excludeId, bool $enforceClinicHours): void
    {
        $date = Carbon::parse($data['appointment_date'])->toDateString();
        $time = $this->normalizeTime((string) $data['appointment_time']);

        $slot = AppointmentTimeSlot::where('slot_time', $time)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $slot) {
            throw ValidationException::withMessages(['appointment_time' => 'Please choose one of the available time slots.']);
        }

        if (! $slot->isOfferedOn($date)) {
            throw ValidationException::withMessages(['appointment_time' => 'This time slot is not offered on '.Carbon::parse($date)->format('l').'s.']);
        }

        if ($enforceClinicHours && ! ClinicHours::isOpenOn($date)) {
            throw ValidationException::withMessages(['appointment_date' => 'The clinic is closed on '.Carbon::parse($date)->format('l').'s. Please choose another date.']);
        }

        if ($this->bookedInSlot($date, $time, $excludeId) >= $slot->max_appointments) {
            throw ValidationException::withMessages(['appointment_time' => 'This time slot is fully booked. Please choose another time.']);
        }

        // Online requests may take only part of a slot (Settings > Appointments > Online requests per time slot).
        $onlineLimit = (int) settings('public_booking_slot_limit', 0);
        if ($onlineLimit > 0 && ($data['source'] ?? null) === Appointment::SOURCE_ONLINE) {
            $online = Appointment::whereDate('appointment_date', $date)
                ->where('appointment_time', $time)
                ->where('source', Appointment::SOURCE_ONLINE)
                ->whereIn('status', self::HOLDING)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->count();
            if ($online >= $onlineLimit) {
                throw ValidationException::withMessages(['appointment_time' => 'This time has no more places for online requests. Please choose another time.']);
            }
        }

        $max = (int) settings('max_daily_appointments', 50);
        if ($max > 0) {
            $day = Appointment::whereDate('appointment_date', $date)
                ->whereIn('status', self::HOLDING)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->count();
            if ($day >= $max) {
                throw ValidationException::withMessages(['appointment_date' => "The daily limit of {$max} appointments has been reached for ".Carbon::parse($date)->format('M d, Y').'. Please choose another date.']);
            }
        }

        if (! empty($data['specialist_visit_id'])) {
            $visit = SpecialistVisit::whereKey($data['specialist_visit_id'])->lockForUpdate()->first();
            if (! $visit || $visit->status !== 'scheduled' || $visit->visit_date->toDateString() !== $date) {
                throw ValidationException::withMessages(['specialist_visit_id' => 'The selected specialist visit is not scheduled on this date.']);
            }
            if ($visit->capacity !== null) {
                $taken = $visit->appointments()
                    ->whereIn('status', self::HOLDING)
                    ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                    ->count();
                if ($taken >= $visit->capacity) {
                    throw ValidationException::withMessages(['specialist_visit_id' => 'The '.$visit->type.' visit on this date is fully booked.']);
                }
            }
        }
    }

    private function bookedInSlot(string $date, string $time, ?int $excludeId): int
    {
        return Appointment::whereDate('appointment_date', $date)
            ->where('appointment_time', $this->normalizeTime($time))
            ->whereIn('status', self::HOLDING)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->count();
    }

    /** "8:00" / "08:00" / "08:00:00" => "08:00:00" (how slot_time is stored). */
    public function normalizeTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($time), $m)) {
            return sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? 0);
        }

        return $time;
    }
}
