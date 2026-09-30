@extends('layouts.app')

@section('title', 'Clinic Visits Calendar')

@section('content')
@php
    $monthKey = $month->format('Y-m');
    $inMonth  = array_filter($counts, fn ($k) => str_starts_with($k, $monthKey), ARRAY_FILTER_USE_KEY);
    $maxDay   = $inMonth ? max($inMonth) : 0;
    // Shading level 0 to 4, relative to the busiest day of the month.
    $level    = fn (int $n) => $n <= 0 || $maxDay <= 0 ? 0 : max(1, (int) ceil($n / $maxDay * 4));
    $keep     = $category !== '' ? ['category' => $category] : [];
    $prevUrl  = route('patient-logs.calendar', $keep + ['month' => $month->subMonthNoOverflow()->format('Y-m')]);
    $nextUrl  = route('patient-logs.calendar', $keep + ['month' => $month->addMonthNoOverflow()->format('Y-m')]);
    $isCurrent = $monthKey === now()->format('Y-m');
    $dayUrl   = fn (string $d) => route('patient-logs.index', ['date' => $d] + $keep);
    $agenda   = collect($weeks)->flatten(1)->filter(fn ($d) => $d['inMonth'] && ($counts[$d['key']] ?? 0) > 0);

    $summary = $monthTotal.' '.Str::plural('visit', $monthTotal).' in '.$month->format('F Y')
        .($category !== '' ? ' for '.$categories[$category] : '').'.'
        .($busiest ? ' Busiest day: '.\Carbon\Carbon::parse($busiest)->format('M j').' ('.$counts[$busiest].').' : '');
@endphp

<x-ui.page-header title="Clinic logbook" :description="$summary">
    @can('create-patient-logs')
    <x-slot:actions>
        <x-ui.button icon="plus-lg" :href="route('patient-logs.create')">Log a visit</x-ui.button>
    </x-slot:actions>
    @endcan
</x-ui.page-header>

<x-ui.tabs class="mb-3" label="Logbook views" active="calendar" :items="[
    'logbook'  => ['label' => 'Logbook', 'icon' => 'list-ul', 'href' => route('patient-logs.index', $keep)],
    'calendar' => ['label' => 'Calendar', 'icon' => 'calendar3', 'href' => route('patient-logs.calendar', $keep + ['month' => $monthKey])],
]" />

<x-ui.filters class="mb-3" :action="route('patient-logs.calendar')" :search="false" :keep="['month']"
    :reset-url="route('patient-logs.calendar', ['month' => $monthKey])"
    :labels="['category' => 'Category']" :options="['category' => $categories]">
    <x-slot:inline>
        <x-ui.select name="category" size="sm" aria-label="Patient category" :options="$categories"
            placeholder="All categories" :selected="$category" />
    </x-slot:inline>
</x-ui.filters>

<x-ui.card flush>
    <x-slot:header>
        <div class="logbook-day">
            <x-ui.button variant="ghost" size="sm" icon="chevron-left" icon-only label="Previous month" :href="$prevUrl" />
            <h2 class="logbook-day-label fs-6 mb-0" aria-live="polite">{{ $month->format('F Y') }}</h2>
            <x-ui.button variant="ghost" size="sm" icon="chevron-right" icon-only label="Next month" :href="$nextUrl" />
            @unless ($isCurrent)
                <x-ui.button variant="link" size="sm" :href="route('patient-logs.calendar', $keep)">This month</x-ui.button>
            @endunless
        </div>
        <div class="visit-cal-legend d-none d-md-flex" aria-hidden="true">
            <span>Fewer</span>
            @for ($i = 1; $i <= 4; $i++)<span class="visit-cal-swatch lvl-{{ $i }}"></span>@endfor
            <span>More visits</span>
        </div>
    </x-slot:header>

    {{-- Month grid (tablet and up) --}}
    <div class="visit-cal d-none d-md-block" role="table" aria-label="Clinic visits, {{ $month->format('F Y') }}">
        <div class="visit-cal-row visit-cal-head" role="row">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                <div class="visit-cal-dow" role="columnheader">{{ $dow }}</div>
            @endforeach
        </div>
        @foreach ($weeks as $week)
            <div class="visit-cal-row" role="row">
                @foreach ($week as $day)
                    @php $n = $counts[$day['key']] ?? 0; @endphp
                    <a href="{{ $dayUrl($day['key']) }}" role="cell"
                       @class(['visit-cal-day', 'lvl-'.$level($n), 'is-outside' => ! $day['inMonth'], 'is-today' => $day['isToday']])
                       aria-label="{{ $day['date']->format('l, F j') }}: {{ $n }} {{ Str::plural('visit', $n) }}{{ $day['isToday'] ? ', today' : '' }}">
                        <span class="visit-cal-num">{{ $day['date']->day }}</span>
                        @if ($n > 0)
                            <span class="visit-cal-count"><strong>{{ $n }}</strong> {{ Str::plural('visit', $n) }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Agenda (phones): only days with visits --}}
    <ul class="visit-agenda d-md-none">
        @forelse ($agenda as $day)
            @php $n = $counts[$day['key']]; @endphp
            <li>
                <a href="{{ $dayUrl($day['key']) }}" @class(['visit-agenda-row', 'is-today' => $day['isToday']])>
                    <span class="visit-cal-swatch lvl-{{ $level($n) }}" aria-hidden="true"></span>
                    <span class="flex-grow-1">{{ $day['date']->format('D, M j') }}@if ($day['isToday']) <x-ui.badge color="brand" size="sm" class="ms-1">Today</x-ui.badge>@endif</span>
                    <span class="tabular fw-semibold">{{ $n }}</span>
                    <span class="text-muted small">{{ Str::plural('visit', $n) }}</span>
                </a>
            </li>
        @empty
            <li><x-ui.empty-state quiet icon="calendar3" title="No visits logged in {{ $month->format('F Y') }}." class="px-3" /></li>
        @endforelse
    </ul>
</x-ui.card>
@endsection
