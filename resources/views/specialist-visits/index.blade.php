@extends('layouts.app')
@use('App\Support\DisplayFormat')

@section('title', 'Specialist visits')

@php
    $typeOptions = $types ? array_combine($types, $types) : [];
    $scopeItems = ['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'];
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Specialist visits"
        description="Days when the doctor or dentist holds clinic. They also show on the appointments calendar."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Specialist visits' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="calendar3" :href="route('appointments.calendar')">Calendar</x-ui.button>
            @can('manage-specialist-visits')
                <x-ui.button icon="plus-lg" :href="route('specialist-visits.create')">Schedule visit</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filters :action="route('specialist-visits.index')" :search="false" :keep="['scope']"
        :labels="['type' => 'Type']" :options="['type' => $typeOptions]">
        <x-slot:pills>
            <x-ui.tabs :items="$scopeItems" :active="$scope" param="scope" label="When" />
        </x-slot:pills>
        <x-slot:inline>
            <x-ui.select name="type" size="sm" aria-label="Type" :options="$typeOptions" placeholder="All types" :selected="$type" />
        </x-slot:inline>
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table :paginator="$visits" noun="visits" caption="Specialist visits" responsive="stack">
            <x-slot:head>
                <x-ui.th>Date</x-ui.th>
                <x-ui.th>Specialist</x-ui.th>
                <x-ui.th priority="md">Hours</x-ui.th>
                <x-ui.th align="end" priority="md">Booked</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>
            @foreach ($visits as $v)
                <tr>
                    <x-ui.td identity>
                        <a href="{{ route('specialist-visits.show', $v) }}" class="fw-semibold">{{ DisplayFormat::date($v->visit_date) }}</a>
                        <span class="text-muted small ms-1">{{ $v->visit_date->format('D') }}</span>
                    </x-ui.td>
                    <x-ui.td label="Specialist" truncate>{{ $v->type }}: {{ $v->specialist_name }}</x-ui.td>
                    <x-ui.td priority="md" label="Hours" muted>{{ $v->time_range }}</x-ui.td>
                    <x-ui.td priority="md" label="Booked" numeric>{{ $v->booked_count }}{{ $v->capacity ? ' / '.$v->capacity : '' }}</x-ui.td>
                    <x-ui.td label="Status"><x-ui.badge :color="$v->status_badge">{{ $v->status_label }}</x-ui.badge></x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$v->type.' on '.DisplayFormat::date($v->visit_date)">
                            <x-ui.action-menu.item :href="route('specialist-visits.show', $v)" icon="eye">View</x-ui.action-menu.item>
                            @can('manage-specialist-visits')
                                <x-ui.action-menu.item :href="route('specialist-visits.edit', $v)" icon="pencil">Edit</x-ui.action-menu.item>
                            @endcan
                            @can('create-appointments')
                                @if ($v->status === 'scheduled' && ! $v->visit_date->lt(today()))
                                    <x-ui.action-menu.item :href="route('appointments.create', ['date' => $v->visit_date->toDateString(), 'specialist_visit_id' => $v->id])" icon="calendar-plus">Book a patient</x-ui.action-menu.item>
                                @endif
                            @endcan
                            @can('manage-specialist-visits')
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item :action="route('specialist-visits.destroy', $v)" method="DELETE" icon="trash" danger
                                    confirm="This cannot be undone." :confirm-title="'Delete the '.$v->type.' visit on '.DisplayFormat::date($v->visit_date).'?'" confirm-button="Delete visit">Delete</x-ui.action-menu.item>
                            @endcan
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-ui.empty-state module="appointments" icon="person-badge" compact
                    :title="$scope === 'upcoming' ? 'No upcoming specialist visits' : 'No specialist visits found'">
                    @can('manage-specialist-visits')
                        <x-ui.button size="sm" icon="plus-lg" :href="route('specialist-visits.create')">Schedule a visit</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
