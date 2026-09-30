{{--
    x-ui.field: label + control + help text + validation message.
    Use it to wrap any control (the x-ui inputs wrap themselves when given a `label`).

    <x-ui.field label="Blood type" name="blood_type" for="blood_type" help="Leave empty if unknown.">
        <select id="blood_type" name="blood_type" class="form-select">...</select>
    </x-ui.field>

    <div class="row g-3">
        <x-ui.field class="col-12 col-sm-6" label="First name" name="first_name" required>...</x-ui.field>
    </div>
--}}
@props([
    'label' => null,
    'name' => null,        // used to look up the validation error
    'for' => null,         // id of the control (defaults to the name-derived id)
    'required' => false,
    'optional' => false,
    'help' => null,
    'bag' => 'default',
])
@php
    $errorKey = $name ? str_replace(['[]', '[', ']'], ['', '.', ''], $name) : null;
    $hasError = $errorKey && isset($errors) && $errors->getBag($bag)->has($errorKey);
    $controlId = $for ?? ($name ? 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-') : null);
@endphp
<div {{ $attributes->class(['c-field']) }}>
    @if ($label)
        <x-ui.label :for="$controlId" :required="$required" :optional="$optional">{{ $label }}</x-ui.label>
    @endif
    {{ $slot }}
    @if ($help && ! $hasError)
        <div class="form-text" @if ($controlId) id="{{ $controlId }}-help" @endif>{{ $help }}</div>
    @endif
    @if ($name)
        <x-ui.field-error :name="$name" :bag="$bag" :id="$controlId ? $controlId.'-error' : null" />
    @endif
</div>
