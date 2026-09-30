@extends('layouts.app')

@section('title', 'New appointment')

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="New appointment" description="Book a clinic appointment for a patient."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Appointments' => route('appointments.index'), 'New appointment' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('appointments.store') }}" novalidate>
        @csrf
        <x-ui.card>
            @include('appointments.partials.form-fields')
            <x-slot:footer class="justify-content-end">
                <x-ui.button variant="secondary" :href="route('appointments.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="calendar-check">Book appointment</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
