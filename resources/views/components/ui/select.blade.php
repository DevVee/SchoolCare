{{--
    x-ui.select
    <x-ui.select name="category" label="Category" :options="$categories" placeholder="Choose a category" required />
    <x-ui.select name="status" :options="['pending' => 'Pending', 'approved' => 'Approved']" :selected="request('status')" placeholder="All statuses" size="sm" />
    <x-ui.select name="tags[]" multiple :options="$tags" :selected="$patient->tags->pluck('id')" />
    <x-ui.select name="grade" label="Grade">  <option value="7">Grade 7</option>  </x-ui.select>   (custom options via slot)

    options:  [value => label] or grouped [groupLabel => [value => label]]
    selected: value or array (defaults to old($name) then `value`)
--}}
@props([
    'name',
    'options' => [],
    'selected' => null,
    'value' => null,          // alias of selected
    'placeholder' => null,    // renders an empty first option
    'label' => null,
    'help' => null,
    'required' => false,
    'optional' => false,
    'disabled' => false,
    'multiple' => false,
    'size' => null,           // sm|lg
    'id' => null,
    'invalid' => null,
    'bag' => 'default',
    'wrapperClass' => null,
])
@php
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $selectId = $id ?? 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $hasError = $invalid ?? (isset($errors) && $errors->getBag($bag)->has($errorKey));
    $current = old($errorKey, $selected ?? $value);
    if ($current instanceof \Illuminate\Support\Collection) {
        $current = $current->all();
    }
    if ($current instanceof \BackedEnum) {
        $current = $current->value;
    }
    $currentValues = array_map('strval', array_filter((array) $current, fn ($v) => $v !== null));
    $isSelected = fn ($v) => in_array((string) $v, $currentValues, true);
    $describedBy = $hasError ? $selectId.'-error' : ($help ? $selectId.'-help' : '');
    $wrap = $label !== null || $help !== null;
    $control = $attributes->class([
        'form-select',
        'form-select-'.$size => in_array($size, ['sm', 'lg'], true),
        'is-invalid' => $hasError,
    ])->merge(array_filter([
        'name' => $name,
        'id' => $selectId,
        'aria-invalid' => $hasError ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]));
@endphp
@php ob_start(); @endphp
<select {{ $control }} @required($required) @disabled($disabled) @if ($multiple) multiple @endif>
    @if ($placeholder !== null && ! $multiple)
        <option value="" @selected(count($currentValues) === 0 || $currentValues === [''])>{{ $placeholder }}</option>
    @endif
    @foreach ($options as $optValue => $optLabel)
        @if (is_array($optLabel))
            <optgroup label="{{ $optValue }}">
                @foreach ($optLabel as $v => $l)
                    <option value="{{ $v }}" @selected($isSelected($v))>{{ $l }}</option>
                @endforeach
            </optgroup>
        @else
            <option value="{{ $optValue }}" @selected($isSelected($optValue))>{{ $optLabel }}</option>
        @endif
    @endforeach
    {{ $slot }}
</select>
@php $controlHtml = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
@if ($wrap)
    <x-ui.field :label="$label" :name="$name" :for="$selectId" :required="$required" :optional="$optional" :help="$help" :bag="$bag" :class="$wrapperClass">{{ $controlHtml }}</x-ui.field>
@else
    {{ $controlHtml }}
    @if ($hasError)<x-ui.field-error :name="$name" :bag="$bag" :id="$selectId.'-error'" />@endif
@endif
