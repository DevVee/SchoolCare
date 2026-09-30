{{--
    x-ui.hero: dashboard banner. Royal-blue band with a subtle same-hue diagonal gradient derived
    from the runtime brand colour and one soft circle at the right (the ONLY gradient in the app).
    Live date and time line (updates every second, pauses while the tab is hidden).

    <x-ui.hero subtitle="Clinic visits, patients and stock at a glance.">
        <x-slot:actions>
            <x-ui.button variant="hero" icon="journal-plus" :href="route('patient-logs.create')">Log a visit</x-ui.button>
            <x-ui.button variant="hero-outline" icon="calendar-check" :href="route('appointments.index')">Appointments</x-ui.button>
        </x-slot:actions>
    </x-ui.hero>

    title:    default "Good morning|afternoon|evening, {first name}" (app timezone)
    subtitle: one line
    clock:    show the live date/time line (default true)
    timezone: default settings('timezone') then config('app.timezone')
    Buttons:  variant="hero" (white, brand text) and variant="hero-outline" (white 40% border).
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'clock' => true,
    'timezone' => null,
    'name' => null,
])
@php
    $tz = $timezone;
    if (! $tz && function_exists('settings')) {
        try { $tz = settings('timezone'); } catch (\Throwable $e) { $tz = null; }
    }
    $tz = $tz ?: config('app.timezone', 'UTC');
    try { $now = now($tz); } catch (\Throwable $e) { $now = now(); $tz = config('app.timezone', 'UTC'); }
    $hour = (int) $now->format('G');
    $part = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
    $first = $name ?? (auth()->check() ? \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() : null);
    $heading = $title ?? ('Good '.$part.($first ? ', '.$first : ''));
@endphp
<section {{ $attributes->class('c-hero') }}>
    <div class="c-hero-text">
        @if ($clock)
            <p class="c-hero-clock" data-live-clock data-timezone="{{ $tz }}">
                <time datetime="{{ $now->toIso8601String() }}">{{ $now->format('l, F j, Y') }} &middot; {{ $now->format('g:i:s A') }}</time>
            </p>
        @endif
        <h1 class="c-hero-title">{{ $heading }}</h1>
        @if ($subtitle)<p class="c-hero-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="c-hero-actions">{{ $actions }}</div>
    @endisset
</section>
