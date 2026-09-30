@extends('layouts.app')

@section('title', $slot->exists ? 'Edit time slot' : 'Add time slot')

@php
    $days = old('weekdays', $slot->weekdays ?: array_keys(\App\Models\AppointmentTimeSlot::WEEKDAYS));
    $days = array_map('intval', (array) $days);
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$slot->exists ? 'Edit time slot' : 'Add a time slot'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Appointment time slots' => route('admin.appointment-slots.index'), ($slot->exists ? 'Edit' : 'Add') => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ $slot->exists ? route('admin.appointment-slots.update', $slot) : route('admin.appointment-slots.store') }}" novalidate>
        @csrf
        @if ($slot->exists) @method('PUT') @endif
        <x-ui.card>
            <x-ui.section title="Time and size" columns="3">
                <x-ui.input type="time" name="slot_time" id="slot-start" label="Start time" required :value="substr((string) $slot->slot_time, 0, 5)" />
                <x-ui.input type="time" name="end_time" id="slot-end" label="End time" required :value="substr((string) $slot->end_time, 0, 5)" />
                <x-ui.input type="number" name="max_appointments" id="slot-cap" label="Patients per slot" required min="1" max="500" :value="$slot->max_appointments" />
                <x-ui.input name="label" id="slot-label" label="Label" optional maxlength="60" wrapper-class="col-full"
                    :value="$slot->label" placeholder="e.g. Morning check-ups" />
            </x-ui.section>

            <x-ui.section title="Offered on" description="Leave all ticked to offer it every day the clinic is open.">
                <fieldset>
                    <legend class="visually-hidden">Offered on</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach (\App\Models\AppointmentTimeSlot::WEEKDAYS as $num => $name)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="weekdays[]" value="{{ $num }}" id="wd-{{ $num }}" @checked(in_array($num, $days, true))>
                                <label class="form-check-label" for="wd-{{ $num }}">{{ $name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <x-ui.field-error name="weekdays" />
                </fieldset>
            </x-ui.section>

            <x-ui.section title="Status">
                <x-ui.switch name="is_active" id="slot-active" label="Active" description="Open for new bookings."
                    :checked="(bool) old('is_active', $slot->is_active)" />
            </x-ui.section>

            <x-slot:footer class="justify-content-end">
                <x-ui.button variant="secondary" :href="route('admin.appointment-slots.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">{{ $slot->exists ? 'Save changes' : 'Add slot' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
