{{--
    x-ui.avatar: photo, or initials on the brand tint.
    <x-ui.avatar :name="$patient->full_name" />
    <x-ui.avatar :src="$user->avatar_url" :name="$user->name" size="lg" />
    <x-ui.avatar :name="auth()->user()->name" size="sm" ring />        (online ring)

    size: xs 24 | sm 32 | md 40 (default) | lg 48 | xl 64
--}}
@props([
    'src' => null,
    'name' => null,
    'size' => 'md',
    'ring' => false,
    'square' => false,
    'alt' => null,
])
@php
    $label = trim((string) $name);
    $words = preg_split('/\s+/', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';
    if (count($words) >= 2) {
        $initials = mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1);
    } elseif (count($words) === 1) {
        $initials = mb_substr($words[0], 0, 2);
    }
    $initials = mb_strtoupper($initials) ?: '?';
@endphp
<span {{ $attributes->class([
    'avatar',
    'avatar-'.$size,
    'avatar-ring' => $ring,
    'avatar-square' => $square,
]) }} @if (! $src) role="img" aria-label="{{ $alt ?? $label }}" @endif>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt ?? $label }}" loading="lazy" decoding="async">
    @else
        <span aria-hidden="true">{{ $initials }}</span>
    @endif
</span>
