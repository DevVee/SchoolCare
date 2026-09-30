{{--
    x-ui.textarea
    <x-ui.textarea name="chief_complaint" label="Chief complaint" rows="3" required :value="$log->chief_complaint" />
    <x-ui.textarea name="notes" label="Notes" optional help="Visible to clinic staff only." />
--}}
@props([
    'name',
    'value' => null,
    'rows' => 4,
    'label' => null,
    'help' => null,
    'required' => false,
    'optional' => false,
    'disabled' => false,
    'readonly' => false,
    'size' => null,     // sm|lg
    'id' => null,
    'invalid' => null,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $fieldId = $id ?? 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $hasError = $invalid ?? (isset($errors) && $errors->getBag($bag)->has($errorKey));
    $current = old($errorKey, $value);
    $describedBy = $hasError ? $fieldId.'-error' : ($help ? $fieldId.'-help' : '');
    $wrap = $label !== null || $help !== null;
    $control = $attributes->class([
        'form-control',
        'form-control-'.$size => in_array($size, ['sm', 'lg'], true),
        'is-invalid' => $hasError,
    ])->merge(array_filter([
        'name' => $name,
        'id' => $fieldId,
        'rows' => $rows,
        'aria-invalid' => $hasError ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]));
@endphp
@php ob_start(); @endphp
<textarea {{ $control }} @required($required) @disabled($disabled) @readonly($readonly)>{{ $current ?? $slot }}</textarea>
@php $controlHtml = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
@if ($wrap)
    <x-ui.field :label="$label" :name="$name" :for="$fieldId" :required="$required" :optional="$optional" :help="$help" :bag="$bag" :class="$wrapperClass">{{ $controlHtml }}</x-ui.field>
@else
    {{ $controlHtml }}
    @if ($hasError)<x-ui.field-error :name="$name" :bag="$bag" :id="$fieldId.'-error'" />@endif
@endif
