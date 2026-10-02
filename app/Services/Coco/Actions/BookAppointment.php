<?php

namespace App\Services\Coco\Actions;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentNotifier;
use App\Services\Coco\PatientFinder;
use Illuminate\Validation\ValidationException;

/**
 * Book one appointment for one patient, the way AppointmentController::store
 * does: pending, staff source, race-safe insert (AppointmentBooking::book),
 * then the usual "appointment booked" SMS/email (AppointmentNotifier).
 */
class BookAppointment extends AppointmentAction
{
    public function type(): string { return 'book_appointment'; }

    public function title(): string { return 'Book appointment'; }

    public function icon(): string { return 'calendar-plus'; }

    public function allowedFor(User $user): bool
    {
        return $user->can('create-appointments');
    }

    public function tool(): array
    {
        $providers = settings()->list('appointment_providers');

        return $this->makeTool(
            'Prepare ONE appointment for ONE patient, as a card. Booked only if the user taps Confirm.',
            array_filter([
                'patient_id'   => ['type' => 'integer'],
                'patient_name' => ['type' => 'string', 'description' => 'Name or patient number as the user wrote it, when there is no id.'],
                'date'         => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'time'         => ['type' => 'string', 'description' => 'Slot start, HH:MM 24-hour.'],
                'purpose'      => ['type' => 'string', 'description' => 'Leave out when the user did not say; the card then uses '.$this->defaultPurpose().'.'],
                'provider'     => $providers ? ['type' => 'string', 'enum' => $providers] : null,
            ]),
            ['date', 'time'],
        );
    }

    /** Used when the user did not say what the visit is for: the first reason offered online. */
    private function defaultPurpose(): string
    {
        return settings()->list('appointment_purposes')[0] ?? 'General Checkup';
    }

    public function propose(array $args, User $user): array
    {
        $date    = $this->parseDate($this->str($args, 'date', 30));
        $time    = $this->parseTime($this->str($args, 'time', 20));
        $purpose = $this->plain($this->str($args, 'purpose', 500));
        $guessed = $purpose === '';
        $purpose = $guessed ? $this->defaultPurpose() : $purpose;

        $provider = $this->str($args, 'provider', 100);
        $options  = settings()->list('appointment_providers');
        $this->need($provider === '' || in_array($provider, $options, true), 'Choose who the appointment is with: '.implode(', ', $options).'.');

        $this->checkWhen($date, $time);

        $patients = $this->finder->resolve($args['patient_id'] ?? null, $args['patient_name'] ?? null);

        return [
            'payload'    => [
                'date' => $date, 'time' => $time, 'purpose' => $purpose, 'provider' => $provider, 'purpose_guessed' => $guessed,
                'candidates' => $patients->map(fn (Patient $p) => (string) $p->id)->values()->all(),
            ],
            'candidates' => $patients->map(fn (Patient $p) => [
                'id' => (string) $p->id, 'label' => $p->full_name, 'detail' => PatientFinder::describe($p),
            ])->values()->all(),
        ];
    }

    public function plan(array $payload, ?string $choice, array $edits, User $user): array
    {
        $this->need($this->allowedFor($user), 'You do not have permission to book appointments.');

        $patient = $this->finder->find($this->chosen($payload, $choice));
        $this->need($patient !== null, 'That patient record is no longer active.');

        $date = (string) $payload['date'];
        $time = (string) $payload['time'];
        $this->need($date >= today()->toDateString(), 'The date has already passed.');
        $this->checkWhen($date, $time);

        $slot  = $this->booking->slot($time);
        $left  = $this->booking->remaining($date, $time);
        $notes = array_values(array_filter([
            ! empty($payload['purpose_guessed']) ? "No reason was given, so it is booked as {$payload['purpose']}. To use another reason, cancel this card and ask again with the reason." : null,
            'It is booked as pending, ready to approve on the Appointments page.',
            $this->smsNote('appointment_created'),
        ]));

        return [
            'summary'  => "Book an appointment for {$patient->full_name} on ".$this->whenLabel($date, $time),
            'fields'   => array_values(array_filter([
                ['label' => 'Patient', 'value' => $patient->full_name.', '.PatientFinder::describe($patient), 'recipient' => true],
                ['label' => 'Date', 'value' => \Carbon\Carbon::parse($date)->format('l, F j, Y')],
                ['label' => 'Time', 'value' => $this->slotLabel($time)],
                ['label' => 'Purpose', 'value' => (string) $payload['purpose']],
                $payload['provider'] ? ['label' => 'With', 'value' => (string) $payload['provider']] : null,
                ['label' => 'Places left', 'value' => $left.' of '.($slot?->max_appointments ?? $left)],
            ])),
            'editable' => null,
            'notes'    => $notes,
            'patient_id' => $patient->id,
            'patient'    => $patient->full_name,
            'date'       => $date,
            'time'       => $time,
            'purpose'    => (string) $payload['purpose'],
            'provider'   => (string) $payload['provider'],
        ];
    }

    public function execute(array $plan, User $user): array
    {
        try {
            $appointment = $this->booking->book([
                'patient_id'       => $plan['patient_id'],
                'appointment_date' => $plan['date'],
                'appointment_time' => $plan['time'],
                'purpose'          => $plan['purpose'],
                'provider'         => $plan['provider'] !== '' ? $plan['provider'] : null,
                'created_by'       => $user->id,
                'status'           => 'pending',
                'source'           => Appointment::SOURCE_STAFF,
            ]);
        } catch (ValidationException $e) {
            return ['ok' => false, 'text' => 'Not booked: '.collect($e->errors())->flatten()->first()];
        }

        app(AppointmentNotifier::class)->notify('created', $appointment);

        return [
            'ok'   => true,
            'text' => "Booked {$plan['patient']} on ".$this->whenLabel($plan['date'], $plan['time']).'. It is pending approval.',
            'url'  => $user->can('view-appointments') ? route('appointments.show', $appointment) : null,
        ];
    }
}
