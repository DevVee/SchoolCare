<?php

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update-consultations');
    }

    public function rules(): array
    {
        return [
            // The patient is locked after creation (see consultations/edit).
            'patient_id'      => ['required', 'integer', Rule::in([(int) $this->route('consultation')?->patient_id])],
            'appointment_id'  => ['nullable', 'integer', Rule::exists('appointments', 'id')
                ->where('patient_id', (int) $this->route('consultation')?->patient_id)
                ->whereNull('deleted_at')],
            'visit_date'      => ['required', 'date', 'before_or_equal:today'],
            'visit_time'      => ['nullable', 'date_format:H:i'],
            'chief_complaint' => ['required', 'string', 'max:1000'],
            'assessment'      => ['nullable', 'string', 'max:2000'],
            'diagnosis'       => ['nullable', 'string', 'max:500'],
            'treatment'       => ['nullable', 'string', 'max:2000'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'      => 'patient',
            'visit_date'      => 'visit date',
            'chief_complaint' => 'chief complaint',
        ];
    }
}
