<?php

namespace App\Http\Requests\Medicine;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-medicines');
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:200'],
            'generic_name'        => ['nullable', 'string', 'max:200'],
            'barcode'             => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('medicines', 'barcode')],
            'purchase_price'      => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'category_id'         => ['required', 'integer', 'exists:medicine_categories,id'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'quantity'            => ['required', 'integer', 'min:0'],
            'unit'                => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::in(settings()->list('medicine_units'))],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'expiration_date'     => ['nullable', 'date'],
            'batch_number'        => ['nullable', 'string', 'max:100'],
            'supplier'            => ['nullable', 'string', 'max:200'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id'         => 'category',
            'low_stock_threshold' => 'low stock threshold',
            'expiration_date'     => 'expiration date',
            'batch_number'        => 'batch number',
            'generic_name'        => 'generic name',
            'purchase_price'      => 'purchase price per unit',
        ];
    }
}
