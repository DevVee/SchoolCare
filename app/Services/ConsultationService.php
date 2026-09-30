<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Consultation;
use Illuminate\Support\Facades\DB;

class ConsultationService
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * Create a consultation and auto-complete its linked appointment
     * (the appointment is validated to belong to the same patient).
     */
    public function create(array $data): Consultation
    {
        return DB::transaction(function () use ($data) {
            $consultation = Consultation::create($data);

            $this->completeLinkedAppointment($data['appointment_id'] ?? null, $data['nurse_id'] ?? null);

            return $consultation;
        });
    }

    /**
     * Update a consultation record. Newly linking an open appointment
     * completes it, mirroring create().
     */
    public function update(Consultation $consultation, array $data): Consultation
    {
        return DB::transaction(function () use ($consultation, $data) {
            $previousAppointment = $consultation->appointment_id;

            $consultation->update($data);

            if (! empty($data['appointment_id']) && (int) $data['appointment_id'] !== (int) $previousAppointment) {
                $this->completeLinkedAppointment($data['appointment_id'], auth()->id());
            }

            return $consultation;
        });
    }

    private function completeLinkedAppointment(mixed $appointmentId, ?int $nurseId): void
    {
        if (empty($appointmentId)) {
            return;
        }

        $appointment = Appointment::find($appointmentId);
        if ($appointment) {
            $this->appointments->completeFromConsultation($appointment, $nurseId);
        }
    }
}
