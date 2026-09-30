{{--
    x-ui.hero: the dashboard's top card. White, a faint grid from the top right, a big headline
    whose words rise into place (ui/life.js), and a live date and time line with a breathing dot
    (updates every second, pauses while the tab is hidden).

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
    Buttons:  variant="hero" (primary) and variant="hero-outline" (secondary).
    Default slot: content under the headline (the dashboard puts the Ask Coco box here).
    aside slot:   a right-hand column (xl and up; stacks below): the dashboard's brief. With an
                  aside, the actions move to the top row beside the clock.
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
@php $split = isset($aside); @endphp
<section {{ $attributes->class(['c-hero', 'c-hero-split' => $split]) }}>
    <span class="c-grid-backdrop" aria-hidden="true"></span>
    <div class="c-hero-text">
        @if ($clock || ($split && isset($actions)))
            <div class="c-hero-top">
                @if ($clock)
                    <p class="c-hero-clock" data-live-clock data-timezone="{{ $tz }}">
                        <span class="c-live" aria-hidden="true"></span>
                        <time datetime="{{ $now->toIso8601String() }}">{{ $now->format('l, F j, Y') }} &middot; {{ $now->format('g:i:s A') }}</time>
                    </p>
                @endif
                @if ($split && isset($actions))
                    <div class="c-hero-actions">{{ $actions }}</div>
                @endif
            </div>
        @endif
        <h1 class="c-hero-title" data-words>{{ $heading }}</h1>
        @if ($subtitle)<p class="c-hero-subtitle">{{ $subtitle }}</p>@endif
        @if (trim($slot) !== '')
            <div class="c-hero-body">{{ $slot }}</div>
        @endif
    </div>
    @if (! $split && isset($actions))
        <div class="c-hero-actions">{{ $actions }}</div>
    @endif
    @if ($split)
        <div class="c-hero-aside">{{ $aside }}</div>
    @endif
</section>
