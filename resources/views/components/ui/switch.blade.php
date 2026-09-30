{{--
    x-ui.switch: on/off toggle. Always submits a value (hidden "0" before the checkbox).
    <x-ui.switch name="sms_enabled" label="Send SMS notifications" :checked="settings('sms_enabled')" />
    <x-ui.switch name="is_active" label="Active" description="Inactive users cannot sign in." :checked="$user->is_active" />
--}}
@props([
    'name',
    'checked' => false,
    'label' => null,
    'description' => null,
    'value' => '1',
    'uncheckedValue' => '0',
    'disabled' => false,
    'size' => 'lg',      // lg (40x24) | md (Bootstrap default)
    'id' => null,
    'bag' => 'default',
])
@php
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $switchId = $id ?? 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $hasError = isset($errors) && $errors->getBag($bag)->has($errorKey);
    $isChecked = session()->hasOldInput() ? (string) old($errorKey) === (string) $value : (bool) $checked;
@endphp
<div {{ $attributes->only('class')->class(['form-check', 'form-switch', 'c-check', 'form-switch-lg' => $size === 'lg']) }}>
    @if ($uncheckedValue !== null)
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
    @endif
    <input {{ $attributes->except('class')->class(['form-check-input', 'is-invalid' => $hasError]) }} type="checkbox" role="switch" name="{{ $name }}" id="{{ $switchId }}" value="{{ $value }}"
        @checked($isChecked) @disabled($disabled) @if ($description) aria-describedby="{{ $switchId }}-desc" @endif>
    @if ($label || $description || trim($slot) !== '')
        <label class="form-check-label" for="{{ $switchId }}">
            {{ $label ?? $slot }}
            @if ($description)<span class="form-check-description" id="{{ $switchId }}-desc">{{ $description }}</span>@endif
        </label>
    @endif
</div>
<x-ui.field-error :name="$name" :bag="$bag" />
