{{-- One "medicine given" row. $index is numeric or the __INDEX__ template token. --}}
@php
    $row      = $row ?? [];
    $medError = $errors->first("medicines.$index.medicine_id");
    $qtyError = $errors->first("medicines.$index.quantity");
@endphp
<div class="medicine-row row g-2 align-items-start mb-3">
    <div class="col-12 col-sm-7">
        <label class="form-label" for="med_{{ $index }}_id">Medicine<span class="form-required" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span></label>
        <select name="medicines[{{ $index }}][medicine_id]" id="med_{{ $index }}_id" required
                @class(['form-select', 'is-invalid' => $medError])
                @if($medError) aria-invalid="true" aria-describedby="med_{{ $index }}_id-error" @endif>
            <option value="">Select a medicine</option>
            @foreach($medicines as $m)
            @php
                $available = (int) ($m->usable_quantity ?? $m->quantity);
                $expiry    = $m->next_expiry ? \App\Support\DisplayFormat::date($m->next_expiry) : '';
            @endphp
            <option value="{{ $m->id }}" data-available="{{ $available }}" data-unit="{{ $m->unit }}" data-expiry="{{ $expiry }}"
                    @selected((string) ($row['medicine_id'] ?? '') === (string) $m->id)>
                {{ $m->name }}{{ $m->generic_name ? ' ('.$m->generic_name.')' : '' }}: {{ $available }} {{ $m->unit }}(s){{ $expiry ? ', exp. '.$expiry : '' }}
            </option>
            @endforeach
        </select>
        <x-ui.field-error name="medicines.{{ $index }}.medicine_id" id="med_{{ $index }}_id-error" />
        <div class="form-text medicine-stock"></div>
    </div>
    <div class="col-7 col-sm-3">
        <label class="form-label" for="med_{{ $index }}_qty">Quantity<span class="form-required" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span></label>
        <input type="number" name="medicines[{{ $index }}][quantity]" id="med_{{ $index }}_qty" min="1" step="1" required
               @class(['form-control', 'tabular', 'is-invalid' => $qtyError])
               @if($qtyError) aria-invalid="true" aria-describedby="med_{{ $index }}_qty-error" @endif
               value="{{ $row['quantity'] ?? 1 }}">
        <x-ui.field-error name="medicines.{{ $index }}.quantity" id="med_{{ $index }}_qty-error" />
    </div>
    <div class="col-5 col-sm-2 medicine-row-remove">
        <span class="form-label d-block invisible" aria-hidden="true">Remove</span>
        <x-ui.button variant="ghost" icon="x-lg" class="remove-medicine w-100" aria-label="Remove this medicine">Remove</x-ui.button>
    </div>
</div>
