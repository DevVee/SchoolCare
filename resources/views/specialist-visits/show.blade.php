@extends('layouts.app')
@use('App\Support\DisplayFormat')

@section('title', $visit->type.' visit, '.$visit->visit_date->format('M d, Y'))

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$visit->type.': '.$visit->specialist_name"
        :description="$visit->visit_date->format('l, F j, Y').', '.$visit->time_range.'.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Specialist visits' => route('specialist-visits.index'), DisplayFormat::date($visit->visit_date) => null]">
        <x-ui.badge :color="$visit->status_badge">{{ $visit->status_label }}</x-ui.badge>
        <x-slot:actions>
            @can('manage-specialist-visits')
                <x-ui.dropdown label="More" variant="secondary">
                    <x-ui.dropdown-item :href="route('specialist-visits.edit', $visit)" icon="pencil">Edit</x-ui.dropdown-item>
                    <x-ui.dropdown-divider />
                    <x-ui.dropdown-item :action="route('specialist-visits.destroy', $visit)" method="DELETE" icon="trash" tone="danger"
                        confirm="This cannot be undone." confirm-title="Delete this specialist visit?" confirm-button="Delete visit">Delete</x-ui.dropdown-item>
                </x-ui.dropdown>
            @endcan
            @can('create-appointments')
                @if ($visit->status === 'scheduled' && ! $visit->visit_date->lt(today()))
                    <x-ui.button icon="calendar-plus" :href="route('appointments.create', ['date' => $visit->visit_date->toDateString(), 'specialist_visit_id' => $visit->id])">Book a patient</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.card title="Visit details" module="appointments" icon="person-badge">
                <x-ui.description-list layout="compact">
                    <x-ui.description-item label="Patient limit">{{ $visit->capacity ?? 'No limit' }}</x-ui.description-item>
                    <x-ui.description-item label="Booked">{{ $visit->bookedCount() }}{{ $visit->capacity ? ', '.$visit->remaining().' left' : '' }}</x-ui.description-item>
                    @if ($visit->notes)
                        <x-ui.description-item label="Notes">{{ $visit->notes }}</x-ui.description-item>
                    @endif
                </x-ui.description-list>
            </x-ui.card>
        </div>
        <div class="col-lg-8">
            <x-ui.card flush title="Appointments for this visit">
                <x-ui.table dense responsive="stack" caption="Appointments for this visit">
                    <x-slot:head><x-ui.th>Time</x-ui.th><x-ui.th>Patient</x-ui.th><x-ui.th priority="md">Purpose</x-ui.th><x-ui.th>Status</x-ui.th></x-slot:head>
                    @foreach ($visit->appointments as $a)
                        <tr>
                            <x-ui.td label="Time" class="tabular">{{ DisplayFormat::time($a->appointment_time) }}</x-ui.td>
                            <x-ui.td identity><a href="{{ route('appointments.show', $a) }}" class="fw-semibold">{{ $a->patient?->full_name ?? $a->requester_name }}</a></x-ui.td>
                            <x-ui.td priority="md" label="Purpose" truncate>{{ $a->purpose }}</x-ui.td>
                            <x-ui.td label="Status"><x-ui.status-badge :status="$a->status" type="appointment" :label="\App\Models\Appointment::statusLabels()[$a->status] ?? null" /></x-ui.td>
                        </tr>
                    @endforeach
                    <x-slot:empty><x-ui.empty-state quiet icon="calendar-x" title="No appointments are linked to this visit yet." /></x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
