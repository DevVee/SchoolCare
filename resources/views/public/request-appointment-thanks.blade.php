@extends('layouts.public')

@section('title', 'Request sent')

@section('content')
<div class="pub-narrow">
    <x-ui.card class="pub-done" padding="lg">
        <span class="pub-done-icon"><x-ui.icon name="check-lg" /></span>
        <h1 class="pub-title">Request sent</h1>
        <p class="pub-intro">Thank you. Your appointment request is now with the clinic staff. You do not need to send it again.</p>

        @if ($summary)
            <dl class="pub-done-summary">
                <div><dt>Date</dt><dd>{{ $summary['date'] }}</dd></div>
                <div><dt>Time</dt><dd>{{ $summary['time'] }}</dd></div>
            </dl>
        @endif

        <h2 class="h6 mt-4 mb-0">What happens next</h2>
        <ol class="pub-done-steps">
            <li><div><strong>The clinic reviews your request</strong>The staff check the date and time against the clinic schedule.</div></li>
            <li><div><strong>You get a text message</strong>The clinic lets you know when it is approved, or if a change is needed.</div></li>
            <li><div><strong>Come to the clinic on time</strong>Bring your school ID. If you can no longer come, please tell the clinic.</div></li>
        </ol>

        <div class="pub-done-actions">
            <x-ui.button :href="route('public.schedule')" variant="secondary" icon="calendar3">Today's clinic schedule</x-ui.button>
            <x-ui.button :href="route('clinic')" variant="ghost">Back to the clinic page</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
