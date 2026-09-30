<?php

namespace App\Http\Requests\Patient;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update-patients');
    }

    private function patient(): ?Patient
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient ? $patient : null;
    }

    public function rules(): array
    {
        return PatientRules::rules($this->patient()) + [
            // Always posted by the edit form (hidden 0 + checkbox 1).
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [PatientRules::academicCheck(fn (string $key) => $this->input($key), $this->patient())];
    }

    public function messages(): array
    {
        return PatientRules::messages();
    }

    /**
     * Normalise the active switch ("on"/"1"/"0") to a real boolean so an
     * unchecked switch actually deactivates the patient.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }
}
