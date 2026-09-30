@extends('layouts.public')

@section('title', 'Form received')

@section('content')
<div class="pub-narrow">
    <x-ui.card class="pub-done" padding="lg">
        <span class="pub-done-icon"><x-ui.icon name="check-lg" /></span>
        <h1 class="pub-title">Form received</h1>
        <p class="pub-intro" style="white-space: pre-line;">{{ $message !== '' ? $message : 'Thank you. The clinic staff will review your form.' }}</p>

        <h2 class="h6 mt-4 mb-0">What happens next</h2>
        <ol class="pub-done-steps">
            <li><div><strong>The clinic staff review your form</strong>They check the details and may contact you if something is missing.</div></li>
            <li><div><strong>It is added to the clinic record</strong>Once approved, the nurse can see the health details when the student visits the clinic.</div></li>
            <li><div><strong>Keep the clinic updated</strong>If anything changes, such as a new allergy or medicine, tell the clinic or send a new form.</div></li>
        </ol>

        <div class="pub-done-actions">
            <x-ui.button :href="url('/')" variant="secondary">Back to the home page</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
