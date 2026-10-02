<?php

namespace App\Services\Coco\Actions;

use App\Exceptions\InvalidStatusTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentService;

/**
 * Cancel one pending or approved appointment with a reason, through
 * AppointmentService::cancel (status guard, audit log and the usual
 * "cancelled" SMS/email), like the Cancel button on the Appointments page.
 */
class CancelAppointment extends AppointmentAction
{
    public function type(): string { return 'cancel_appointment'; }

    public function title(): string { return 'Cancel appointment'; }

    public function icon(): string { return 'calendar-x'; }

    public function allowedFor(User $user): bool
    {
        return $user->can('cancel-appointments');
    }

    public function tool(): array
    {
        return $this->makeTool(
            'Prepare cancelling ONE pending or approved appointment, as a card. Cancelled only if the user taps Confirm. Give appointment_id, or the patient.',
            [
                'appointment_id' => ['type' => 'integer'],
                'patient_id'     => ['type' => 'integer'],
                'patient_name'   => ['type' => 'string'],
                'on_date'        => ['type' => 'string', 'description' => 'YYYY-MM-DD, when the patient has several.'],
                'reason'         => ['type' => 'string', 'description' => 'As the user said; ask if they did not.'],
            ],
            ['reason'],
        );
    }

    public function propose(array $args, User $user): array
    {
        $reason = $this->checkReason($this->plain($this->str($args, 'reason', 1000)));

        $appointments = $this->openAppointments($args)->filter(fn (Appointment $a) => $a->canTransitionTo('cancelled'));
        $this->need($appointments->isNotEmpty(), 'That appointment cannot be cancelled.');

        return [
            'payload'    => ['reason' => $reason, 'candidates' => $appointments->map(fn (Appointment $a) => (string) $a->id)->values()->all()],
            'candidates' => $appointments->map(fn (Appointment $a) => $this->appointmentCandidate($a))->values()->all(),
        ];
    }

    public function plan(array $payload, ?string $choice, array $edits, User $user): array
    {
        $this->need($this->allowedFor($user), 'You do not have permission to cancel appointments.');

        $appointment = Appointment::with('patient')->find($this->chosen($payload, $choice));
        $this->need($appointment !== null, 'That appointment no longer exists.');
        $this->need($appointment->canTransitionTo('cancelled'), 'That appointment is already closed and cannot be cancelled.');

        $reason = $this->checkReason((string) $payload['reason']);
        $when   = $this->whenLabel($appointment->appointment_date->toDateString(), (string) $appointment->appointment_time);

        return [
            'summary'  => "Cancel {$appointment->display_name}'s appointment on {$when}",
            'fields'   => array_values(array_filter([
                ['label' => 'Patient', 'value' => $appointment->display_name, 'recipient' => true],
                ['label' => 'When', 'value' => $when, 'recipient' => true],
                ['label' => 'Status', 'value' => Appointment::statusLabels()[$appointment->status] ?? $appointment->status, 'recipient' => true],
                ['label' => 'Purpose', 'value' => (string) $appointment->purpose, 'recipient' => true],
                $reason !== '' ? ['label' => 'Reason', 'value' => $reason] : null,
            ])),
            'editable' => null,
            'notes'    => array_values(array_filter([$this->smsNote('appointment_cancelled')])),
            'appointment_id' => $appointment->id,
            'patient'        => $appointment->display_name,
            'when'           => $when,
            'reason'         => $reason,
        ];
    }

    public function execute(array $plan, User $user): array
    {
        $appointment = Appointment::find($plan['appointment_id']);
        if (! $appointment) {
            return ['ok' => false, 'text' => 'Not cancelled: the appointment was removed.'];
        }

        try {
            app(AppointmentService::class)->cancel($appointment, $plan['reason']);
        } catch (InvalidStatusTransition $e) {
            return ['ok' => false, 'text' => 'Not cancelled: '.$e->getMessage()];
        }

        return [
            'ok'   => true,
            'text' => "Cancelled {$plan['patient']}'s appointment on {$plan['when']}.",
            'url'  => $user->can('view-appointments') ? route('appointments.show', $appointment) : null,
        ];
    }

    /** Admin > Settings > Appointments > "Require a reason when cancelling". */
    private function checkReason(string $reason): string
    {
        if ((bool) settings('appointment_cancel_reason_required', true)) {
            $this->need($reason !== '', 'A reason is needed to cancel. Ask the user why it is cancelled.');
        }
        $this->need(mb_strlen($reason) <= 500, 'The reason is too long. Keep it under 500 characters.');

        return $reason;
    }
}
