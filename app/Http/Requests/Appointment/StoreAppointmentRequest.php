<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-appointments');
    }

    public function rules(): array
    {
        return [
            // Archived (soft-deleted) patients cannot be booked.
            'patient_id'       => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['required', 'string', Rule::exists('appointment_time_slots', 'slot_time')->where('is_active', true)],
            'purpose'          => ['required', 'string', 'max:500'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            // Who the appointment is with (SSCMS appointee) and an optional specialist clinic day.
            'provider'            => ['nullable', 'string', Rule::in(settings()->list('appointment_providers'))],
            'specialist_visit_id' => ['nullable', 'integer', Rule::exists('specialist_visits', 'id')->where('status', 'scheduled')],
        ];
    }

    /** Reject time slots that have already passed today. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $when = \Carbon\Carbon::parse($this->input('appointment_date') . ' ' . $this->input('appointment_time'));
                if ($when->isToday() && $when->isPast()) {
                    $validator->errors()->add('appointment_time', 'This time slot has already passed today. Please choose a later time.');
                }
            },
            // Booking limits from Admin → Settings → Appointments.
            function (Validator $validator) {
                if ($validator->errors()->has('appointment_date')) {
                    return;
                }
                foreach (static::bookingLimitErrors((string) $this->input('appointment_date')) as $error) {
                    $validator->errors()->add('appointment_date', $error);
                }
            },
        ];
    }

    /**
     * Settings-driven booking rules for a date: weekends, booking window and
     * the daily maximum (pending + approved appointments).
     *
     * @return string[] error messages (empty when the date is allowed)
     */
    public static function bookingLimitErrors(string $date, ?int $excludeId = null): array
    {
        try {
            $day = \Carbon\Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return [];
        }

        $errors = [];

        if (! settings('allow_weekend_booking', true) && $day->isWeekend()) {
            $errors[] = 'Appointments cannot be booked on weekends.';
        }

        $window = (int) settings('booking_max_days_ahead', 0);
        if ($window > 0 && $day->gt(today()->addDays($window))) {
            $errors[] = "Appointments can only be booked up to {$window} days ahead.";
        }

        $max = (int) settings('max_daily_appointments', 50);
        if ($max > 0) {
            $booked = \App\Models\Appointment::whereDate('appointment_date', $day->toDateString())
                ->whereIn('status', ['pending', 'approved'])
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->count();

            if ($booked >= $max) {
                $errors[] = "The daily limit of {$max} appointments has been reached for ".$day->format('M d, Y').'. Please choose another date.';
            }
        }

        return $errors;
    }

    public function messages(): array
    {
        return [
            'patient_id.required'       => 'Please select a patient.',
            'patient_id.exists'         => 'Selected patient not found or archived.',
            'appointment_time.exists'   => 'Please choose one of the available time slots.',
            'appointment_date.required' => 'Appointment date is required.',
            'appointment_date.after_or_equal' => 'Appointment date must be today or in the future.',
            'appointment_time.required' => 'Please select a time slot.',
            'purpose.required'          => 'Please describe the purpose of the visit.',
        ];
    }
}
