<?php

namespace App\Services\Coco\Actions;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentNotifier;
use Illuminate\Validation\ValidationException;

/**
 * Move one pending or approved appointment to another date and time, the way
 * AppointmentController::update does: AppointmentBooking::reschedule (capacity
 * re-checked in the transaction), then the usual "rescheduled" notice.
 */
class RescheduleAppointment extends AppointmentAction
{
    public function type(): string { return 'reschedule_appointment'; }

    public function title(): string { return 'Move appointment'; }

    public function icon(): string { return 'calendar-event'; }

    public function allowedFor(User $user): bool
    {
        return $user->can('update-appointments');
    }

    public function tool(): array
    {
        return $this->makeTool(
            'Prepare moving ONE pending or approved appointment, as a card. Moved only if the user taps Confirm. Give appointment_id, or the patient.',
            [
                'appointment_id' => ['type' => 'integer'],
                'patient_id'     => ['type' => 'integer'],
                'patient_name'   => ['type' => 'string'],
                'current_date'   => ['type' => 'string', 'description' => 'YYYY-MM-DD, when the patient has several.'],
                'date'           => ['type' => 'string', 'description' => 'New date, YYYY-MM-DD.'],
                'time'           => ['type' => 'string', 'description' => 'New slot start, HH:MM 24-hour.'],
            ],
            ['date', 'time'],
        );
    }

    public function propose(array $args, User $user): array
    {
        $date = $this->parseDate($this->str($args, 'date', 30));
        $time = $this->parseTime($this->str($args, 'time', 20));

        $appointments = $this->openAppointments($args);

        if ($appointments->count() === 1) {
            $this->checkMove($appointments->first(), $date, $time);
        } else {
            $this->checkWhen($date, $time);
        }

        return [
            'payload'    => ['date' => $date, 'time' => $time, 'candidates' => $appointments->map(fn (Appointment $a) => (string) $a->id)->values()->all()],
            'candidates' => $appointments->map(fn (Appointment $a) => $this->appointmentCandidate($a))->values()->all(),
        ];
    }

    public function plan(array $payload, ?string $choice, array $edits, User $user): array
    {
        $this->need($this->allowedFor($user), 'You do not have permission to change appointments.');

        $appointment = Appointment::with('patient')->find($this->chosen($payload, $choice));
        $this->need($appointment !== null, 'That appointment no longer exists.');
        $this->need($appointment->isEditable(), 'That appointment is already closed and cannot be moved.');

        $date = (string) $payload['date'];
        $time = (string) $payload['time'];
        $this->checkMove($appointment, $date, $time);

        $from = $this->whenLabel($appointment->appointment_date->toDateString(), (string) $appointment->appointment_time);

        return [
            'summary'  => "Move {$appointment->display_name}'s appointment from {$from} to ".$this->whenLabel($date, $time),
            'fields'   => array_values(array_filter([
                ['label' => 'Patient', 'value' => $appointment->display_name, 'recipient' => true],
                ['label' => 'Now', 'value' => $from, 'recipient' => true],
                ['label' => 'Move to', 'value' => \Carbon\Carbon::parse($date)->format('l, F j, Y').', '.$this->slotLabel($time)],
                ['label' => 'Purpose', 'value' => (string) $appointment->purpose, 'recipient' => true],
            ])),
            'editable' => null,
            'notes'    => array_values(array_filter([$this->smsNote('appointment_rescheduled')])),
            'appointment_id' => $appointment->id,
            'patient'        => $appointment->display_name,
            'date'           => $date,
            'time'           => $time,
        ];
    }

    public function execute(array $plan, User $user): array
    {
        $appointment = Appointment::find($plan['appointment_id']);
        if (! $appointment || ! $appointment->isEditable()) {
            return ['ok' => false, 'text' => 'Not moved: the appointment is closed or was removed.'];
        }

        try {
            $this->booking->reschedule($appointment, array_filter([
                'appointment_date'    => $plan['date'],
                'appointment_time'    => $plan['time'],
                // Kept as is, so its capacity is checked on the same day.
                'specialist_visit_id' => $appointment->specialist_visit_id,
            ], fn ($v) => $v !== null));
        } catch (ValidationException $e) {
            return ['ok' => false, 'text' => 'Not moved: '.collect($e->errors())->flatten()->first()];
        }

        if (AppointmentNotifier::wasRescheduled($appointment)) {
            app(AppointmentNotifier::class)->notify('rescheduled', $appointment);
        }

        return [
            'ok'   => true,
            'text' => "Moved {$plan['patient']}'s appointment to ".$this->whenLabel($plan['date'], $plan['time']).'.',
            'url'  => $user->can('view-appointments') ? route('appointments.show', $appointment) : null,
        ];
    }

    private function checkMove(Appointment $appointment, string $date, string $time): void
    {
        $sameDate = $appointment->appointment_date->toDateString() === $date;
        $sameTime = $this->booking->normalizeTime((string) $appointment->appointment_time) === $time;

        $this->need(! ($sameDate && $sameTime), 'The appointment is already at that date and time.');
        $this->need($sameDate || ! $appointment->specialist_visit_id,
            'This appointment belongs to a specialist visit day. Move it to another day from the Appointments page.');

        $this->checkWhen($date, $time, $appointment->id, ! $sameDate);
    }
}
