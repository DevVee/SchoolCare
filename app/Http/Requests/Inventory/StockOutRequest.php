<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'batch_id'    => ['nullable', 'integer', 'exists:medicine_batches,id'],
            'notes'       => ['required', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'medicine_id' => 'medicine',
            'batch_id'    => 'batch',
        ];
    }
}
