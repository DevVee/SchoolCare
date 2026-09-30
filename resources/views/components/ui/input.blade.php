{{--
    x-ui.input: text-like input with old() + validation state.
    With `label` (or `help`) it renders the full field (label, control, help, error).

    <x-ui.input name="first_name" label="First name" required :value="$patient->first_name" />
    <x-ui.input name="email" type="email" label="Email" help="Used for password resets." />
    <x-ui.input name="birthdate" type="date" label="Birthdate" optional />
    <x-ui.input name="q" icon="search" placeholder="Search" size="sm" />              (bare control)
    <x-ui.input name="items[0][qty]" type="number" label="Qty" />                       (error key items.0.qty)

    Any other attribute (min, max, step, autocomplete, x-*, data-*, class) goes on the <input>.
--}}
@props([
    'name',
    'type' => 'text',
    'value' => null,
    'label' => null,
    'help' => null,
    'required' => false,
    'optional' => false,
    'disabled' => false,
    'readonly' => false,
    'size' => null,        // sm|lg
    'icon' => null,        // leading icon
    'id' => null,
    'invalid' => null,     // force the invalid state (default: has a validation error)
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $inputId = $id ?? 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $hasError = $invalid ?? (isset($errors) && $errors->getBag($bag)->has($errorKey));
    $current = $type === 'password' ? null : old($errorKey, $value);
    if ($current instanceof \DateTimeInterface) {
        $current = $current->format($type === 'date' ? 'Y-m-d' : ($type === 'datetime-local' ? 'Y-m-d\TH:i' : ($type === 'time' ? 'H:i' : 'Y-m-d H:i:s')));
    }
    $describedBy = $hasError ? $inputId.'-error' : ($help ? $inputId.'-help' : '');
    $wrap = $label !== null || $help !== null;
    $control = $attributes->class([
        'form-control',
        'form-control-'.$size => in_array($size, ['sm', 'lg'], true),
        'is-invalid' => $hasError,
    ])->merge(array_filter([
        'type' => $type,
        'name' => $name,
        'id' => $inputId,
        'aria-invalid' => $hasError ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]));
@endphp
@php ob_start(); @endphp
@if ($icon)<div class="input-icon"><x-ui.icon :name="$icon" />@endif
<input {{ $control }} @if ($current !== null && $current !== '') value="{{ $current }}" @endif @required($required) @disabled($disabled) @readonly($readonly)>
@if ($icon)</div>@endif
@php $controlHtml = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
@if ($wrap)
    <x-ui.field :label="$label" :name="$name" :for="$inputId" :required="$required" :optional="$optional" :help="$help" :bag="$bag" :class="$wrapperClass">{{ $controlHtml }}</x-ui.field>
@else
    {{ $controlHtml }}
    @if ($hasError)<x-ui.field-error :name="$name" :bag="$bag" :id="$inputId.'-error'" />@endif
@endif
