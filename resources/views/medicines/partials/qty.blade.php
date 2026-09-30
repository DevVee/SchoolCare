{{--
    Quantity with its unit, for right-aligned numeric cells: "1,240 tablets".
    @include('medicines.partials.qty', ['qty' => $med->quantity, 'unit' => $med->unit])
    Optional 'sign' => true prefixes positive numbers with "+".
--}}
@php
    $qtyValue = (int) ($qty ?? 0);
    $qtyUnit = trim((string) ($unit ?? ''));
    $qtyUnitText = $qtyUnit === '' || in_array(strtolower($qtyUnit), ['ml', 'mg', 'g', 'l', 'mcg', 'iu', 'other'], true)
        ? $qtyUnit
        : \Illuminate\Support\Str::plural($qtyUnit, abs($qtyValue));
@endphp
<span class="fw-semibold tabular">{{ ($sign ?? false) && $qtyValue > 0 ? '+' : '' }}{{ number_format($qtyValue) }}</span>@if ($qtyUnitText !== '') <span class="text-muted fs-xs">{{ $qtyUnitText }}</span>@endif
