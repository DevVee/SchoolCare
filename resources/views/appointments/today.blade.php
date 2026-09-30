@extends('layouts.app')
@use('App\Support\DisplayFormat')

@section('title', $date->isToday() ? "Today's appointments" : 'Appointments on '.$date->format('M d, Y'))

@php
    $byStatus = $appointments->groupBy('status')->map->count();
    $statusTones = ['pending' => 'warning', 'approved' => 'success', 'completed' => 'appointments', 'cancelled' => 'danger', 'no_show' => 'slate'];
    $statusIcons = ['pending' => 'hourglass-split', 'approved' => 'calendar-check', 'completed' => 'check2-all', 'cancelled' => 'x-circle', 'no_show' => 'person-slash'];
    $hoursText = $hours ? 'Clinic hours '.DisplayFormat::timeRange($hours['open'], $hours['close']) : 'The clinic is closed on this day';
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$date->isToday() ? 'Today' : $date->format('l, M j')"
        :description="$date->format('l, F j, Y').'. '.$hoursText.'.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Appointments' => route('appointments.index'), ($date->isToday() ? 'Today' : $date->format('M d, Y')) => null]">
        <x-slot:actions>
            <div class="btn-group" role="group" aria-label="Change day">
                <x-ui.button variant="secondary" icon-only icon="chevron-left" label="Previous day" :href="route('appointments.today', ['date' => $date->copy()->subDay()->toDateString()])" />
                @unless ($date->isToday())
                    <x-ui.button variant="secondary" :href="route('appointments.today')">Today</x-ui.button>
                @endunless
                <x-ui.button variant="secondary" icon-only icon="chevron-right" label="Next day" :href="route('appointments.today', ['date' => $date->copy()->addDay()->toDateString()])" />
            </div>
            @can('create-appointments')
                <x-ui.button icon="calendar-plus" :href="route('appointments.create', ['date' => $date->toDateString()])">Book</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('appointments.partials.tabs', ['active' => 'today'])

    <x-ui.stat-cards cols="5">
        @foreach ($statusLabels as $status => $label)
            <x-ui.stat-card :label="$label" :value="$byStatus[$status] ?? 0" :tone="$statusTones[$status] ?? 'appointments'"
                :icon="$statusIcons[$status] ?? 'calendar'" :href="route('appointments.index', ['date' => $date->toDateString(), 'status' => $status])" />
        @endforeach
    </x-ui.stat-cards>

    <div class="row g-3">
        <div class="col-lg-8">
            <x-ui.card flush title="Schedule" module="appointments">
                <x-ui.table responsive="stack" caption="Schedule">
                    <x-slot:head>
                        <x-ui.th width="96px">Time</x-ui.th>
                        <x-ui.th>Patient</x-ui.th>
                        <x-ui.th priority="md">Purpose</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                    </x-slot:head>
                    @foreach ($byTime as $time => $group)
                        @foreach ($group as $appt)
                            @php $name = $appt->patient?->full_name ?? $appt->requester_name; @endphp
                            <tr>
                                <x-ui.td label="Time" class="tabular fw-semibold">@if ($loop->first){{ DisplayFormat::time($time) }}@else<span class="text-muted fw-normal">{{ DisplayFormat::time($time) }}</span>@endif</x-ui.td>
                                <x-ui.td identity>
                                    <div class="identity">
                                        <x-ui.avatar :name="$name" size="sm" />
                                        <div class="identity-text">
                                            <a href="{{ route('appointments.show', $appt) }}" class="identity-title">{{ $name }}</a>
                                            <span class="identity-sub">
                                                @if ($appt->needsPatientLink())<span class="text-warning-emphasis">Not linked</span>@endif
                                                @if ($appt->isOnlineRequest()){{ $appt->needsPatientLink() ? ', ' : '' }}Online request @endif
                                                @if ($appt->provider){{ $appt->isOnlineRequest() || $appt->needsPatientLink() ? ', ' : '' }}with {{ $appt->provider }}@endif
                                            </span>
                                        </div>
                                    </div>
                                </x-ui.td>
                                <x-ui.td priority="md" label="Purpose" truncate>{{ $appt->purpose }}</x-ui.td>
                                <x-ui.td label="Status"><x-ui.status-badge :status="$appt->status" type="appointment" :label="$statusLabels[$appt->status] ?? null" /></x-ui.td>
                                <x-ui.td actions>
                                    <x-ui.action-menu :for="$name.' at '.DisplayFormat::time($time)">
                                        <x-ui.action-menu.item :href="route('appointments.show', $appt)" icon="eye">View</x-ui.action-menu.item>
                                        @can('approve', $appt)
                                            <x-ui.action-menu.item :action="route('appointments.approve', $appt)" method="PATCH" icon="check-circle">Approve</x-ui.action-menu.item>
                                        @endcan
                                        @can('complete', $appt)
                                            <x-ui.action-menu.item :action="route('appointments.complete', $appt)" method="PATCH" icon="check2-all">Mark completed</x-ui.action-menu.item>
                                        @endcan
                                    </x-ui.action-menu>
                                </x-ui.td>
                            </tr>
                        @endforeach
                    @endforeach
                    <x-slot:empty>
                        <x-ui.empty-state module="appointments" title="No appointments on this day" compact>
                            @can('create-appointments')
                                <x-ui.button size="sm" variant="secondary" icon="calendar-plus" :href="route('appointments.create', ['date' => $date->toDateString()])">Book appointment</x-ui.button>
                            @endcan
                        </x-ui.empty-state>
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>

        <div class="col-lg-4 vstack gap-3">
            @if ($visits->isNotEmpty())
                <x-ui.card flush title="Specialist visits" module="appointments" icon="person-badge">
                    <ul class="list-unstyled mb-0">
                        @foreach ($visits as $v)
                            <li @class(['px-3 py-2 small', 'border-top' => ! $loop->first])>
                                <a href="{{ route('specialist-visits.show', $v) }}" class="fw-semibold">{{ $v->type }}: {{ $v->specialist_name }}</a>
                                <div class="text-muted">{{ $v->time_range }}, {{ $v->status_label }}</div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card flush title="Places left per slot">
                <x-ui.table dense caption="Places left per slot">
                    <x-slot:head><x-ui.th>Time</x-ui.th><x-ui.th align="end">Booked</x-ui.th><x-ui.th align="end">Left</x-ui.th></x-slot:head>
                    @foreach ($slots as $s)
                        <tr @class(['text-muted' => $s['past']])>
                            <x-ui.td>{{ $s['slot']->display_label }}</x-ui.td>
                            <x-ui.td numeric>{{ $s['booked'] }}</x-ui.td>
                            <x-ui.td numeric @class(['text-danger fw-semibold' => $s['remaining'] === 0])>{{ $s['remaining'] === 0 ? 'Full' : $s['remaining'] }}</x-ui.td>
                        </tr>
                    @endforeach
                    <x-slot:empty><x-ui.empty-state quiet icon="clock" title="No time slots are offered on this day." /></x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
