<?php

namespace App\Services\Coco\Actions;

use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentBooking;
use App\Services\Coco\CocoRefusal;
use App\Services\Coco\PatientFinder;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Date, time and capacity checks shared by the appointment actions. They are
 * the checks of the Appointments page (StoreAppointmentRequest and
 * AppointmentBooking), run again on Confirm, where AppointmentBooking re-counts
 * inside the booking transaction.
 */
abstract class AppointmentAction extends CocoAction
{
    public function __construct(
        protected readonly AppointmentBooking $booking,
        protected readonly PatientFinder $finder,
        protected readonly SmsService $sms,
    ) {}

    public function group(): string { return 'appointments'; }

    /** "2026-10-01" from what the model sent. */
    protected function parseDate(string $value): string
    {
        $this->need($value !== '', 'Give the date as YYYY-MM-DD.');

        try {
            $date = Carbon::parse($value, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            throw CocoRefusal::because("\"{$value}\" is not a date. Give the date as YYYY-MM-DD.");
        }

        $this->need($date->gte(today()), 'The date has already passed. Pick today or a later date.');

        return $date->toDateString();
    }

    /** "09:00:00" from "9:00", "09:00" or "9:00 AM". */
    protected function parseTime(string $value): string
    {
        $this->need($value !== '', 'Give the time as HH:MM, 24-hour.');

        if (preg_match('/[ap]\.?m\.?/i', $value)) {
            try {
                $value = Carbon::parse($value)->format('H:i');
            } catch (\Throwable) {
                throw CocoRefusal::because("\"{$value}\" is not a time. Give the time as HH:MM, 24-hour.");
            }
        }

        $time = $this->booking->normalizeTime($value);
        $this->need((bool) preg_match('/^\d{2}:\d{2}:\d{2}$/', $time), "\"{$value}\" is not a time. Give the time as HH:MM, 24-hour.");

        return $time;
    }

    /**
     * Refuse a date and time the Appointments page would refuse: not a slot,
     * not offered that weekday, already past, weekends off, outside the
     * booking window, daily limit reached, or the slot is full.
     */
    protected function checkWhen(string $date, string $time, ?int $excludeId = null, bool $dateChanged = true): void
    {
        $slot = $this->booking->slot($time);
        $day  = Carbon::parse($date);

        if (! $slot) {
            throw CocoRefusal::because(Carbon::parse($time)->format('g:i A').' is not one of the appointment times. '.$this->freeTimes($date));
        }

        if (! $slot->isOfferedOn($date)) {
            throw CocoRefusal::because('That time is not offered on '.$day->format('l').'s. '.$this->freeTimes($date));
        }

        if (Carbon::parse($date.' '.$time)->isPast()) {
            throw CocoRefusal::because('That time has already passed today. '.$this->freeTimes($date));
        }

        if ($dateChanged) {
            $errors = StoreAppointmentRequest::bookingLimitErrors($date, $excludeId);
            if ($errors !== []) {
                throw CocoRefusal::because($errors[0]);
            }
        }

        if ($this->booking->remaining($date, $time, $excludeId) < 1) {
            throw CocoRefusal::because('That time is fully booked. '.$this->freeTimes($date));
        }
    }

    /** "Free times on Wed, Oct 1: 08:00, 08:30." for the refusal messages. */
    protected function freeTimes(string $date): string
    {
        $free = $this->booking->slotsForDate($date)
            ->filter(fn ($s) => $s['available'])
            ->map(fn ($s) => substr((string) $s['slot']->slot_time, 0, 5))
            ->values();

        $day = Carbon::parse($date)->format('D, M j');

        return $free->isEmpty()
            ? "No free times on {$day}."
            : "Free times on {$day}: ".$free->take(12)->implode(', ').($free->count() > 12 ? ', and more' : '').'.';
    }

    protected function whenLabel(string $date, string $time): string
    {
        return Carbon::parse($date)->format('D, M j, Y').' at '.Carbon::parse($time)->format('g:i A');
    }

    /** The slot as the booking form shows it, e.g. "Morning (08:00 AM to 08:30 AM)". */
    protected function slotLabel(string $time): string
    {
        return $this->booking->slot($time)?->display_label ?? Carbon::parse($time)->format('h:i A');
    }

    /** "The patient gets a text about this." when that SMS goes out. */
    protected function smsNote(string $event): ?string
    {
        return $this->sms->enabled() && $this->sms->eventEnabled($event)
            ? 'The patient (or guardian) gets a text message about this, as usual.'
            : null;
    }

    /**
     * Open appointments the model means: by id, else the patient's upcoming
     * pending or approved ones (on one date when given).
     *
     * @return Collection<int, Appointment>
     */
    protected function openAppointments(array $args): Collection
    {
        if (filled($args['appointment_id'] ?? null) && is_numeric($args['appointment_id'])) {
            $appointment = Appointment::with('patient')->find((int) $args['appointment_id']);
            $this->need($appointment !== null, 'No appointment has that id. Use list_appointments to find it.');
            $this->need(! $appointment->isTerminal(), 'That appointment is already '.strtolower(Appointment::statusLabels()[$appointment->status] ?? $appointment->status).' and cannot be changed.');

            return collect([$appointment]);
        }

        $patients = $this->finder->resolve($args['patient_id'] ?? null, $args['patient_name'] ?? null);
        $date     = $this->str($args, 'current_date', 20) ?: $this->str($args, 'on_date', 20);

        $appointments = Appointment::with('patient')
            ->whereIn('patient_id', $patients->pluck('id'))
            ->whereIn('status', ['pending', 'approved'])
            ->when($date !== '', fn ($q) => $q->whereDate('appointment_date', $this->safeDate($date)))
            ->when($date === '', fn ($q) => $q->whereDate('appointment_date', '>=', today()))
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(PatientFinder::LIMIT)
            ->get();

        $this->need($appointments->isNotEmpty(), $patients->count() === 1
            ? 'This patient has no pending or approved appointment'.($date !== '' ? ' on that date' : ' from today on').'.'
            : 'None of the matching patients has a pending or approved appointment'.($date !== '' ? ' on that date' : ' from today on').'.');

        return $appointments;
    }

    protected function appointmentCandidate(Appointment $a): array
    {
        return [
            'id'     => (string) $a->id,
            'label'  => $a->display_name,
            'detail' => $this->whenLabel($a->appointment_date->toDateString(), (string) $a->appointment_time).', '.(Appointment::statusLabels()[$a->status] ?? $a->status),
            'meta'   => (string) $a->purpose,
        ];
    }

    private function safeDate(string $value): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw CocoRefusal::because("\"{$value}\" is not a date. Give the date as YYYY-MM-DD.");
        }
    }
}
