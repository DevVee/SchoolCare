<?php

namespace App\Http\Requests\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update-appointments');
    }

    public function rules(): array
    {
        /** @var Appointment|null $appointment */
        $appointment = $this->route('appointment');
        $currentDate = $appointment?->appointment_date?->toDateString();

        $dateRules = ['required', 'date'];
        // Rescheduling must move to today or later; keeping the existing
        // date (e.g. editing notes on an overdue appointment) is allowed.
        if ($this->input('appointment_date') !== $currentDate) {
            $dateRules[] = 'after_or_equal:today';
        }

        return [
            // Archived patients cannot be booked; the appointment's current
            // patient is still accepted so older records remain editable.
            // An unlinked online request may stay unlinked while it is edited.
            'patient_id'       => [$appointment?->patient_id ? 'required' : 'nullable', 'integer', Rule::exists('patients', 'id')->where(
                fn ($q) => $q->whereNull('deleted_at')->orWhere('id', $appointment?->patient_id ?? 0)
            )],
            'appointment_date' => $dateRules,
            'appointment_time' => ['required', 'string', Rule::exists('appointment_time_slots', 'slot_time')->where('is_active', true)],
            'purpose'          => ['required', 'string', 'max:500'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'provider'            => ['nullable', 'string', Rule::in([...settings()->list('appointment_providers'), ...array_filter([$appointment?->provider])])],
            'specialist_visit_id' => ['nullable', 'integer', Rule::exists('specialist_visits', 'id')->where(
                fn ($q) => $q->where('status', 'scheduled')->orWhere('id', $appointment?->specialist_visit_id ?? 0)
            )],
        ];
    }

    /** Booking limits (Admin → Settings → Appointments) apply when the date changes. */
    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator) {
                $appointment = $this->route('appointment');
                $date = (string) $this->input('appointment_date');
                if ($validator->errors()->has('appointment_date') || ! $appointment
                    || $date === $appointment->appointment_date?->toDateString()) {
                    return;
                }
                foreach (StoreAppointmentRequest::bookingLimitErrors($date, $appointment->id) as $error) {
                    $validator->errors()->add('appointment_date', $error);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.exists'               => 'Selected patient not found or archived.',
            'appointment_date.after_or_equal' => 'Appointment date must be today or in the future.',
            'appointment_time.exists'         => 'Please choose one of the available time slots.',
        ];
    }
}
