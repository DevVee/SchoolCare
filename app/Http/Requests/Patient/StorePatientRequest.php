<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-patients');
    }

    public function rules(): array
    {
        return PatientRules::rules() + [
            // Optional: link an online appointment request to the new patient.
            'link_appointment_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [PatientRules::academicCheck(fn (string $key) => $this->input($key))];
    }

    public function messages(): array
    {
        return PatientRules::messages();
    }
}
