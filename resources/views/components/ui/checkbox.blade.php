{{--
    x-ui.checkbox
    <x-ui.checkbox name="is_pwd" label="Person with disability" :checked="$patient->is_pwd" />
    <x-ui.checkbox name="notify" label="Send SMS" description="Guardian receives a text when the visit is logged." unchecked-value="0" />
    <x-ui.checkbox name="roles[]" value="nurse" label="Nurse" :checked="in_array('nurse', $roles)" />
    <x-ui.checkbox name="terms" type="radio" value="a" label="Option A" />

    After a failed validation the state comes from old input (for array names: in_array).
    `unchecked-value` adds a hidden input so an unchecked box still submits a value.
--}}
@props([
    'name',
    'value' => '1',
    'checked' => false,
    'label' => null,
    'description' => null,
    'type' => 'checkbox',       // checkbox|radio
    'uncheckedValue' => null,
    'inline' => false,
    'disabled' => false,
    'required' => false,
    'id' => null,
    'bag' => 'default',
])
@php
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $boxId = $id ?? 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name.'-'.$value), '-');
    $hasError = isset($errors) && $errors->getBag($bag)->has($errorKey);
    if (session()->hasOldInput()) {
        $old = old($errorKey);
        $isChecked = is_array($old) ? in_array((string) $value, array_map('strval', $old), true) : ((string) $old === (string) $value);
    } else {
        $isChecked = (bool) $checked;
    }
@endphp
<div {{ $attributes->only('class')->class(['form-check', 'c-check', 'form-check-inline' => $inline]) }}>
    @if ($uncheckedValue !== null && $type === 'checkbox')
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
    @endif
    <input {{ $attributes->except('class')->class(['form-check-input', 'is-invalid' => $hasError]) }} type="{{ $type }}" name="{{ $name }}" id="{{ $boxId }}" value="{{ $value }}"
        @checked($isChecked) @disabled($disabled) @required($required) @if ($description) aria-describedby="{{ $boxId }}-desc" @endif>
    @if ($label || $description || trim($slot) !== '')
        <label class="form-check-label" for="{{ $boxId }}">
            {{ $label ?? $slot }}
            @if ($description)<span class="form-check-description" id="{{ $boxId }}-desc">{{ $description }}</span>@endif
        </label>
    @endif
</div>
