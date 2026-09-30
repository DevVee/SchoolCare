@extends('layouts.app')

@section('title', 'Appointment time slots')

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Appointment time slots"
        description="The times patients can be booked, how many per slot, and on which days. Used by staff booking and online requests."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Administration' => null, 'Appointment time slots' => null]">
        <x-slot:actions>
            <x-ui.button icon="plus-lg" :href="route('admin.appointment-slots.create')">Add slot</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        <x-ui.table caption="Appointment time slots" responsive="stack">
            <x-slot:head>
                <x-ui.th>Time</x-ui.th>
                <x-ui.th priority="md">Label</x-ui.th>
                <x-ui.th align="end">Per slot</x-ui.th>
                <x-ui.th priority="lg">Days</x-ui.th>
                <x-ui.th align="end" priority="md">Upcoming</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>
            @foreach ($slots as $slot)
                @php
                    $used = (int) ($usage[$slot->slot_time] ?? 0);
                    $range = \App\Support\DisplayFormat::timeRange($slot->slot_time, $slot->end_time);
                @endphp
                <tr @class(['text-muted' => ! $slot->is_active])>
                    <x-ui.td identity><a href="{{ route('admin.appointment-slots.edit', $slot) }}" class="fw-semibold">{{ $range }}</a></x-ui.td>
                    <x-ui.td priority="md" label="Label" truncate>{{ $slot->label ?: '-' }}</x-ui.td>
                    <x-ui.td numeric label="Per slot">{{ $slot->max_appointments }}</x-ui.td>
                    <x-ui.td priority="lg" label="Days" truncate>{{ $slot->weekdays_label }}</x-ui.td>
                    <x-ui.td numeric priority="md" label="Upcoming">{{ (int) ($upcoming[$slot->slot_time] ?? 0) }}</x-ui.td>
                    <x-ui.td label="Status"><x-ui.status-badge :status="(bool) $slot->is_active" /></x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$range">
                            <x-ui.action-menu.item :href="route('admin.appointment-slots.edit', $slot)" icon="pencil">Edit</x-ui.action-menu.item>
                            @if ($slot->is_active)
                                <x-ui.action-menu.item :action="route('admin.appointment-slots.toggle', $slot)" method="PATCH" icon="pause-circle"
                                    confirm="No new appointments can be booked in this slot. Existing ones are kept." :confirm-title="'Deactivate '.$range.'?'" confirm-button="Deactivate">Deactivate</x-ui.action-menu.item>
                            @else
                                <x-ui.action-menu.item :action="route('admin.appointment-slots.toggle', $slot)" method="PATCH" icon="play-circle">Activate</x-ui.action-menu.item>
                            @endif
                            <x-ui.action-menu.divider />
                            @if ($used === 0)
                                <x-ui.action-menu.item :action="route('admin.appointment-slots.destroy', $slot)" method="DELETE" icon="trash" danger
                                    confirm="This cannot be undone." :confirm-title="'Delete the '.$range.' slot?'" confirm-button="Delete slot">Delete</x-ui.action-menu.item>
                            @else
                                <x-ui.action-menu.item icon="lock" disabled :title="'Used by '.$used.' '.\Illuminate\Support\Str::plural('appointment', $used).'. Deactivate it instead.'">Delete (in use)</x-ui.action-menu.item>
                            @endif
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-ui.empty-state module="appointments" icon="clock" compact title="No time slots yet"
                    description="Appointments cannot be booked until at least one slot is active.">
                    <x-ui.button size="sm" icon="plus-lg" :href="route('admin.appointment-slots.create')">Add a slot</x-ui.button>
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>

    <p class="small text-muted mb-0">A slot used by appointments cannot be deleted. Deactivate it to stop new bookings. Clinic open days are set in Settings, Appointments, Weekly opening hours.</p>
</div>
@endsection
