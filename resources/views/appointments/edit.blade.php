@extends('layouts.app')

@section('title', 'Edit appointment #' . $appointment->id)

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Edit appointment" :description="'Appointment #'.$appointment->id.': '.$appointment->display_name"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Appointments' => route('appointments.index'), '#'.$appointment->id => route('appointments.show', $appointment), 'Edit' => null]" />

    @if (! $appointment->isEditable())
        <x-ui.alert variant="warning">
            This appointment is <strong>{{ \App\Models\Appointment::statusLabels()[$appointment->status] }}</strong> and can no longer be edited.
        </x-ui.alert>
    @elseif ($appointment->isApproved())
        <x-ui.alert variant="info">This appointment is already approved. Changing the date or time sends a rescheduled notice when text messages are on.</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('appointments.update', $appointment) }}" novalidate>
        @csrf @method('PUT')
        <x-ui.card>
            @include('appointments.partials.form-fields', ['appointment' => $appointment])
            <x-slot:footer class="justify-content-end">
                <x-ui.button variant="secondary" :href="route('appointments.show', $appointment)">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg" :disabled="! $appointment->isEditable()">Save changes</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
