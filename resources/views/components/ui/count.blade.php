{{--
    x-ui.count: small NEUTRAL number for nav items, tabs and headings. Caps at "99+".
    <x-ui.count :value="$pendingAppointments" />
    <x-ui.count :value="3" urgent label="3 overdue" />      (red only when urgent AND actionable)

    Renders nothing when value is null or 0 (unless show-zero).
--}}
@props([
    'value' => null,
    'max' => 99,
    'urgent' => false,
    'brand' => false,
    'showZero' => false,
    'label' => null,   // accessible text, e.g. "5 pending"
])
@php
    $n = is_numeric($value) ? (int) $value : null;
    $visible = $n !== null && ($n > 0 || $showZero);
    $text = $n !== null && $n > $max ? $max.'+' : (string) $n;
@endphp
@if ($visible)
    <span {{ $attributes->class(['count-chip', 'count-chip-urgent' => $urgent, 'count-chip-brand' => $brand && ! $urgent]) }}
          @if ($label) aria-label="{{ $label }}" @endif>{{ $text }}</span>
@endif
