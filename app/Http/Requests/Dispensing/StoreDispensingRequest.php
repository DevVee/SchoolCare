<?php

namespace App\Http\Requests\Dispensing;

use App\Models\Consultation;
use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDispensingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-dispensing');
    }

    public function rules(): array
    {
        return [
            // Archived (soft-deleted) patients cannot receive new dispensing records.
            'patient_id'      => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'consultation_id' => ['nullable', 'integer', Rule::exists('consultations', 'id')->whereNull('deleted_at')],
            'medicine_id'     => ['required', 'integer', Rule::exists('medicines', 'id')->whereNull('deleted_at')],
            'quantity'        => ['required', 'integer', 'min:1', 'max:100000'],
            'remarks'         => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Clinical safety checks: never dispense expired, inactive or
     * out-of-stock medicine, and never link another patient's consultation.
     * DispensingService re-checks inside a locked transaction as well.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $medicine = Medicine::find($this->integer('medicine_id'));
                if ($medicine) {
                    // Batch-aware: only unexpired, undisposed batches count (FEFO pool).
                    $available = $medicine->availableQuantity();
                    if (! $medicine->is_active) {
                        $validator->errors()->add('medicine_id', "\"{$medicine->name}\" is inactive and cannot be dispensed.");
                    } elseif ($available === 0 && $medicine->quantity > 0) {
                        $validator->errors()->add('medicine_id', "All remaining stock of \"{$medicine->name}\" is expired and cannot be dispensed.");
                    } elseif ($available < $this->integer('quantity')) {
                        $validator->errors()->add('quantity', "Insufficient stock for \"{$medicine->name}\". Available: {$available} {$medicine->unit}(s), requested: {$this->integer('quantity')}.");
                    }
                }

                if ($this->filled('consultation_id')) {
                    $consultation = Consultation::find($this->integer('consultation_id'));
                    if ($consultation && (int) $consultation->patient_id !== $this->integer('patient_id')) {
                        $validator->errors()->add('consultation_id', 'The selected consultation belongs to a different patient.');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'      => 'patient',
            'medicine_id'     => 'medicine',
            'consultation_id' => 'consultation',
        ];
    }
}
