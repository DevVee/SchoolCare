{{--
    x-ui.date-range: "From [date] to [date]" on one line (two inputs side by side on phones).
    Made for the `inline` slot of x-ui.filters, but works in any form.

    <x-ui.date-range from-name="date_from" to-name="date_to" :max="$today" />
    <x-ui.date-range :from="$f['dateFrom']" :to="$f['dateTo']" label="Visit dates" />

    from / to:  values (default: request(from-name) / request(to-name), after old() input)
    min / max:  applied to both inputs (to-input min also follows the from value when set)
    label:      accessible group name (default "Date range")
    Register both names in x-ui.filters `labels` so they get chips and count as active.
--}}
@props([
    'fromName' => 'from',
    'toName' => 'to',
    'from' => null,
    'to' => null,
    'min' => null,
    'max' => null,
    'label' => 'Date range',
    'size' => 'sm',          // sm|md
    'bag' => 'default',
])
@php
    $fmt = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_string($v) ? $v : null);
    $fromVal = $fmt(old($fromName, $from ?? request($fromName)));
    $toVal = $fmt(old($toName, $to ?? request($toName)));
    $minVal = $fmt($min);
    $maxVal = $fmt($max);
    $errs = isset($errors) ? $errors->getBag($bag) : null;
    $fromErr = $errs?->has($fromName);
    $toErr = $errs?->has($toName);
    $base = 'f-'.trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $fromName.'-'.$toName), '-');
    $inputClass = fn ($err) => trim('form-control'.($size === 'sm' ? ' form-control-sm' : '').($err ? ' is-invalid' : ''));
@endphp
<div {{ $attributes->class('date-range-field') }}>
    <div class="date-range" role="group" aria-label="{{ $label }}">
        <span class="date-range-text date-range-from-text" aria-hidden="true">From</span>
        <input type="date" name="{{ $fromName }}" id="{{ $base }}-from" class="{{ $inputClass($fromErr) }}" aria-label="From date"
               @if ($fromVal) value="{{ $fromVal }}" @endif @if ($minVal) min="{{ $minVal }}" @endif @if ($maxVal) max="{{ $maxVal }}" @endif
               @if ($fromErr) aria-invalid="true" @endif>
        <span class="date-range-text" aria-hidden="true">to</span>
        <input type="date" name="{{ $toName }}" id="{{ $base }}-to" class="{{ $inputClass($toErr) }}" aria-label="To date"
               @if ($toVal) value="{{ $toVal }}" @endif @if ($fromVal || $minVal) min="{{ $fromVal ?? $minVal }}" @endif @if ($maxVal) max="{{ $maxVal }}" @endif
               @if ($toErr) aria-invalid="true" @endif>
    </div>
    <x-ui.field-error :name="$fromName" :bag="$bag" />
    <x-ui.field-error :name="$toName" :bag="$bag" />
</div>
