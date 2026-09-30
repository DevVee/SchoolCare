@extends('layouts.app')

@section('title', 'Appointments calendar')

@php
    $tones = ['pending' => 'warning', 'approved' => 'success', 'completed' => 'brand', 'cancelled' => 'danger', 'no_show' => 'neutral'];
    $items = [];
    foreach ($counts as $date => $byStatus) {
        foreach ($statusLabels as $status => $label) {
            if (! empty($byStatus[$status])) {
                $items[$date][] = [
                    'label' => $label,
                    'count' => $byStatus[$status],
                    'tone'  => $tones[$status] ?? 'neutral',
                    'url'   => route('appointments.index', ['date' => $date, 'status' => $status]),
                    'title' => $byStatus[$status].' '.strtolower($label).' on '.$date,
                ];
            }
        }
    }
    foreach ($visits as $date => $dayVisits) {
        foreach ($dayVisits as $v) {
            $items[$date][] = [
                'label' => $v->type.' '.\App\Support\DisplayFormat::time($v->start_time),
                'icon'  => 'person-badge',
                'url'   => route('specialist-visits.show', $v),
                'title' => $v->type.': '.$v->specialist_name.', '.$v->time_range,
            ];
        }
    }
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Appointments"
        :description="$monthTotal.' '.\Illuminate\Support\Str::plural('appointment', $monthTotal).' in '.$month->format('F Y').'. Select a count to see those appointments.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Appointments' => route('appointments.index'), 'Calendar' => null]">
        <x-slot:actions>
            @can('manage-specialist-visits')
                <x-ui.button variant="secondary" icon="person-badge" :href="route('specialist-visits.create')">Schedule specialist</x-ui.button>
            @endcan
            @can('create-appointments')
                <x-ui.button icon="calendar-plus" :href="route('appointments.create')">New appointment</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('appointments.partials.tabs', ['active' => 'calendar'])

    @include('partials.month-calendar', [
        'month'      => $month,
        'weeks'      => $weeks,
        'items'      => $items,
        'navRoute'   => 'appointments.calendar',
        'navQuery'   => [],
        'dayUrl'     => fn ($d) => route('appointments.today', ['date' => $d]),
        'closedDays' => $closedDays,
    ])

    <div class="month-cal-legend" aria-label="Legend">
        @foreach ($statusLabels as $status => $label)
            <span><span class="month-cal-dot tone-{{ $tones[$status] ?? 'neutral' }}"></span>{{ $label }}</span>
        @endforeach
        @can('view-specialist-visits')
            <span><x-ui.icon name="person-badge" class="tone-appointments" />Specialist visit</span>
        @endcan
    </div>
</div>
@endsection
