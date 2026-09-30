{{--
    x-ui.label
    <x-ui.label for="first_name" required>First name</x-ui.label>
    <x-ui.label for="middle_name" optional>Middle name</x-ui.label>
--}}
@props([
    'for' => null,
    'required' => false,
    'optional' => false,
    'value' => null,     // text (alternative to the slot)
])
<label {{ $attributes->class('form-label')->merge(array_filter(['for' => $for])) }}>{{ $value ?? $slot }}@if ($required)<span class="form-required" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span>@elseif ($optional)<span class="form-optional">Optional</span>@endif</label>
