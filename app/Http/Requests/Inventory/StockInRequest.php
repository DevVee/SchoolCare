<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'medicine_id'     => ['required', 'integer', 'exists:medicines,id'],
            'quantity'        => ['required', 'integer', 'min:1'],
            'batch_number'    => ['nullable', 'string', 'max:100'],
            // MED-2 FIX: Prevent stocking in already-expired medicines.
            // after:today ensures the expiry date is in the future.
            'expiration_date' => ['nullable', 'date', 'after:today'],
            'supplier'        => ['nullable', 'string', 'max:200'],
            'unit_cost'       => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'expiration_date.after' => 'The expiration date must be a future date. Expired medicines cannot be stocked in.',
        ];
    }

    public function attributes(): array
    {
        return [
            'medicine_id'     => 'medicine',
            'expiration_date' => 'expiration date',
            'batch_number'    => 'batch / lot number',
            'unit_cost'       => 'cost per unit',
        ];
    }
}
