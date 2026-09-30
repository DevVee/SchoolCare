{{--
    x-ui.field-error: validation message for one field (renders nothing when valid).
    <x-ui.field-error name="email" />
    <x-ui.field-error name="items.0.qty" id="qty-error" />
--}}
@props([
    'name',
    'bag' => 'default',
])
@php
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $bagErrors = isset($errors) ? $errors->getBag($bag) : null;
    $message = $bagErrors?->first($errorKey);
@endphp
@if ($message)
    <div {{ $attributes->class(['invalid-feedback', 'd-flex']) }}>
        <x-ui.icon name="exclamation-circle" /><span>{{ $message }}</span>
    </div>
@endif
