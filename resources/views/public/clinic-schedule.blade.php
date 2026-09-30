@extends('layouts.public')

@section('title', 'Clinic schedule')
@section('nav', 'schedule')

@php
    $fmt = \App\Support\DisplayFormat::class;
    $openCount = $slots->where('available', true)->count();
    $todayKey = strtolower($today->englishDayOfWeek);

    // Next day the clinic is open (shown when it is closed today).
    $nextOpen = null;
    if (! $hours) {
        for ($i = 1; $i <= 7; $i++) {
            $d = $today->copy()->addDays($i);
            $h = $week[strtolower($d->englishDayOfWeek)] ?? null;
            if ($h) { $nextOpen = ['date' => $d, 'hours' => $h]; break; }
        }
    }
@endphp

@section('content')
<div class="pub-schedule-head">
    <div>
        <h1 class="pub-title">Clinic schedule</h1>
        <p class="pub-today">
            <span>{{ $today->format('l, F j, Y') }}</span>
            @if ($hours)
                <x-ui.badge color="success">Open {{ $fmt::timeRange($hours['open'], $hours['close']) }}</x-ui.badge>
            @else
                <x-ui.badge color="neutral">Closed today</x-ui.badge>
            @endif
        </p>
    </div>
    <x-ui.button :href="route('public.appointments.create')" icon="calendar-plus">Request an appointment</x-ui.button>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <x-ui.card title="Appointment times today"
            :subtitle="$slots->isNotEmpty() ? ($openCount === 1 ? '1 time still open' : ($openCount ? $openCount.' times still open' : 'No open times left today')) : null">
            @if ($slots->isEmpty())
                @if ($hours)
                    <x-ui.empty-state compact icon="calendar-x" title="No appointment times today"
                        description="You can still visit the clinic during opening hours, or request a later date." />
                @else
                    <x-ui.empty-state compact icon="door-closed" title="The clinic is closed today"
                        :description="$nextOpen ? 'Next open '.$nextOpen['date']->format('l, F j').', '.$fmt::timeRange($nextOpen['hours']['open'], $nextOpen['hours']['close']).'.' : 'Please check the weekly hours.'" />
                @endif
            @else
                <ul class="pub-slots">
                    @foreach ($slots as $s)
                        @php
                            $state = $s['past'] ? 'past' : ($s['remaining'] > 0 ? 'open' : 'full');
                        @endphp
                        <li class="pub-slot is-{{ $state }}">
                            <span class="pub-slot-time">{{ $s['slot']->display_label }}</span>
                            <span class="pub-slot-state">
                                <span class="pub-slot-dot" aria-hidden="true"></span>
                                @if ($state === 'past')
                                    Time passed
                                @elseif ($state === 'open')
                                    {{ $s['remaining'] === 1 ? '1 place left' : $s['remaining'].' places left' }}
                                @else
                                    Full
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <div class="col-lg-4 d-flex flex-column gap-4">
        <x-ui.card title="Doctor and dentist today">
            @if ($visits->isEmpty())
                <p class="text-muted small mb-0">No doctor or dentist visit today. The school nurse is available during clinic hours.</p>
            @else
                <ul class="pub-list">
                    @foreach ($visits as $v)
                        <li>
                            <span class="pub-list-main">{{ $v->type }}</span>
                            <span class="pub-list-sub">{{ $v->time_range }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        @if ($upcomingVisits->isNotEmpty())
            <x-ui.card title="Coming up" subtitle="Doctor and dentist visits in the next two weeks">
                <ul class="pub-list">
                    @foreach ($upcomingVisits as $v)
                        <li>
                            <span class="pub-list-main">{{ $v->type }}</span>
                            <span class="pub-list-sub">{{ $v->visit_date->format('D, M j') }}<br>{{ $v->time_range }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card title="Weekly hours">
            <ul class="pub-list">
                @foreach ($week as $day => $h)
                    <li @class(['is-today' => $todayKey === $day])>
                        <span class="pub-list-main">{{ ucfirst($day) }}@if ($todayKey === $day)<span class="visually-hidden"> (today)</span>@endif</span>
                        <span class="pub-list-sub">{{ $h ? $fmt::timeRange($h['open'], $h['close']) : 'Closed' }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</div>
@endsection
