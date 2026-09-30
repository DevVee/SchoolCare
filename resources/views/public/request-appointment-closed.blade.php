{{--
    Shown at /request-appointment while Admin > Settings > Appointments >
    "Accept online appointment requests" is off. The message is set on the same page.
--}}
@extends('layouts.public')

@section('title', 'Online requests are closed')
@section('nav', 'request')

@php
    $phone = trim((string) settings('clinic_contact'));
    $email = trim((string) settings('clinic_email'));
@endphp

@section('content')
<div class="pub-narrow">
    <x-ui.card class="pub-done pub-closed" padding="lg">
        <span class="pub-done-icon"><x-ui.icon name="calendar-x" /></span>
        <h1 class="pub-title">Online requests are closed</h1>
        <p class="pub-intro">{{ $message !== '' ? $message : 'The clinic is not taking online appointment requests right now. Please visit the clinic during clinic hours or call the clinic.' }}</p>

        @if ($phone !== '' || $email !== '')
            <ul class="pub-closed-contact">
                @if ($phone !== '')
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"><x-ui.icon name="telephone" />{{ $phone }}</a></li>
                @endif
                @if ($email !== '')
                    <li><a href="mailto:{{ $email }}"><x-ui.icon name="envelope" />{{ $email }}</a></li>
                @endif
            </ul>
        @endif

        <div class="pub-done-actions">
            <x-ui.button :href="route('clinic')" icon="arrow-left">Back to the clinic page</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
