@extends('layouts.app')

@section('title', 'Appointments')

@php
    $sourceOptions = ['staff' => 'Booked by staff', 'online' => 'Online requests', 'unlinked' => 'Not linked to a patient'];
    $providerOptions = $providers ? array_combine($providers, $providers) : [];
    $statusItems = ['' => ['label' => 'All', 'count' => $counts->sum()]];
    foreach ($statusLabels as $val => $label) {
        $statusItems[$val] = ['label' => $label, 'count' => $counts[$val] ?? 0];
    }
    $filtered = $filters['search'] || $filters['date'] || $filters['source'] || $filters['provider'] || $filters['status'];
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Appointments" description="Clinic appointments booked by staff and requested online."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Appointments' => null]">
        @can('create-appointments')
            <x-slot:actions>
                <x-ui.button :href="route('appointments.create')" icon="calendar-plus">New appointment</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    @include('appointments.partials.tabs', ['active' => 'list'])

    <x-ui.stat-cards cols="4">
        <x-ui.stat-card label="Pending" :value="$counts['pending'] ?? 0" tone="appointments" icon="hourglass-split"
            :href="route('appointments.index', ['status' => 'pending'])">Waiting for approval</x-ui.stat-card>
        <x-ui.stat-card label="Approved today" :value="$todayCounts['approved'] ?? 0" tone="appointments" icon="calendar-check"
            :href="route('appointments.today')">
            <span class="stat-meta-item"><span class="stat-dot tone-amber"></span>Pending {{ $todayCounts['pending'] ?? 0 }}</span>
            <span class="stat-meta-item"><span class="stat-dot tone-green"></span>Done {{ $todayCounts['completed'] ?? 0 }}</span>
        </x-ui.stat-card>
        <x-ui.stat-card label="Online requests" :value="$onlinePending" tone="appointments" icon="globe2"
            :href="route('appointments.index', ['source' => 'online', 'status' => 'pending'])">Pending requests from the public form</x-ui.stat-card>
        <x-ui.stat-card label="Not linked" :value="$unlinkedCount" :tone="$unlinkedCount ? 'warning' : 'appointments'" icon="link-45deg"
            :href="route('appointments.index', ['source' => 'unlinked'])">
            @if ($unlinkedCount)<span class="stat-mark mark-warn">Link to a patient</span> before approving @else All requests are linked @endif
        </x-ui.stat-card>
    </x-ui.stat-cards>

    <x-ui.filters :action="route('appointments.index')" search-placeholder="Patient name or number" :keep="['status']"
        :labels="['date' => 'Date', 'source' => 'Source', 'provider' => 'With']"
        :options="['source' => $sourceOptions]">
        <x-slot:inline>
            <x-ui.input type="date" name="date" size="sm" aria-label="Date" :value="$filters['date']" />
        </x-slot:inline>
        <x-ui.select name="source" label="Source" size="sm" :options="$sourceOptions" placeholder="All" :selected="$filters['source']" />
        @if ($providerOptions)
            <x-ui.select name="provider" label="With" size="sm" :options="$providerOptions" placeholder="Anyone" :selected="$filters['provider']" />
        @endif
        <x-slot:pills>
            <x-ui.tabs :items="$statusItems" :active="$filters['status']" param="status" label="Appointment status" />
        </x-slot:pills>
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table :paginator="$appointments" noun="appointments" caption="Appointments" responsive="stack">
            <x-slot:head>
                <x-ui.th>Patient</x-ui.th>
                <x-ui.th>Date and time</x-ui.th>
                <x-ui.th priority="md">Purpose</x-ui.th>
                <x-ui.th priority="xl">With</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th priority="lg">Source</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($appointments as $appt)
                @php $name = $appt->patient?->full_name ?? ($appt->requester_name ?: 'Unknown'); @endphp
                <tr>
                    <x-ui.td identity>
                        <div class="identity">
                            <x-ui.avatar :name="$name" size="sm" />
                            <div class="identity-text">
                                @if ($appt->patient)
                                    @can('view-patients')
                                        <a href="{{ route('patients.show', $appt->patient->id) }}" class="identity-title">{{ $appt->patient->full_name }}</a>
                                    @else
                                        <span class="identity-title">{{ $appt->patient->full_name }}</span>
                                    @endcan
                                    <span class="identity-sub tabular">{{ $appt->patient->patient_number }}@if ($appt->patient->trashed()), archived @endif</span>
                                @else
                                    <a href="{{ route('appointments.show', $appt) }}" class="identity-title">{{ $name }}</a>
                                    <span class="identity-sub text-warning-emphasis">Not linked to a patient</span>
                                @endif
                            </div>
                        </div>
                    </x-ui.td>
                    <x-ui.td label="Date and time">
                        <a href="{{ route('appointments.show', $appt) }}" class="text-reset">{{ $appt->appointment_date->format('M d, Y') }}</a>
                        <span class="text-muted">{{ \Carbon\Carbon::parse($appt->appointment_time)->format('h:i A') }}</span>
                    </x-ui.td>
                    <x-ui.td priority="md" label="Purpose" truncate>{{ $appt->purpose }}</x-ui.td>
                    <x-ui.td priority="xl" label="With" muted truncate>{{ $appt->provider ?: '-' }}</x-ui.td>
                    <x-ui.td label="Status"><x-ui.status-badge :status="$appt->status" type="appointment" :label="$statusLabels[$appt->status] ?? null" /></x-ui.td>
                    <x-ui.td priority="lg" label="Source">
                        @if ($appt->isOnlineRequest())
                            <x-ui.badge color="info" icon="globe2" :dot="false">Online request</x-ui.badge>
                        @else
                            <span class="text-muted" title="Booked by {{ $appt->createdBy?->name ?? 'a deleted user' }}">Staff</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$name.', '.$appt->appointment_date->format('M d')">
                            <x-ui.action-menu.item :href="route('appointments.show', $appt)" icon="eye">View</x-ui.action-menu.item>
                            @can('update', $appt)
                                <x-ui.action-menu.item :href="route('appointments.edit', $appt)" icon="pencil">Edit</x-ui.action-menu.item>
                            @endcan
                            @can('approve', $appt)
                                <x-ui.action-menu.item :action="route('appointments.approve', $appt)" method="PATCH" icon="check-circle">Approve</x-ui.action-menu.item>
                            @endcan
                            @can('cancel', $appt)
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item icon="x-circle" danger class="btn-cancel" :data-action="route('appointments.cancel', $appt)">
                                    {{ $appt->isOnlineRequest() && $appt->isPending() ? 'Decline request' : 'Cancel appointment' }}
                                </x-ui.action-menu.item>
                            @endcan
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($filtered)
                    <x-ui.empty-state icon="search" title="No appointments match these filters" description="Try another date or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('appointments.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state module="appointments" title="No appointments yet" description="Booked and requested appointments show up here." compact>
                        @can('create-appointments')
                            <x-ui.button size="sm" icon="calendar-plus" :href="route('appointments.create')">Book appointment</x-ui.button>
                        @endcan
                    </x-ui.empty-state>
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>

@include('appointments.partials.cancel-modal', ['reasonRequired' => $reasonRequired])
@endsection
