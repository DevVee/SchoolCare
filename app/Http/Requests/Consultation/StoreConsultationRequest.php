<?php

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-consultations');
    }

    public function rules(): array
    {
        return [
            // Archived patients cannot get new consultations.
            'patient_id'      => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            // The linked appointment must be open and belong to the same patient
            // (it is auto-completed when the consultation is saved).
            'appointment_id'  => ['nullable', 'integer', Rule::exists('appointments', 'id')
                ->where('patient_id', (int) $this->input('patient_id'))
                ->whereIn('status', ['pending', 'approved'])
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
            'appointment_id'  => 'appointment',
            'visit_date'      => 'visit date',
            'visit_time'      => 'visit time',
            'chief_complaint' => 'chief complaint',
        ];
    }
}
