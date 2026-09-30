@extends('layouts.app')

@section('title', 'Send a text')

@php
    $connected = filled(config('semaphore.api_key'));
    $smsOn = (bool) settings('sms_enabled');
    $sender = trim((string) settings('sms_sender_name', '')) ?: (string) config('semaphore.sender_name', '');
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Send a text" description="Send a one-off text message to a patient, parent or staff member."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'SMS log' => route('sms.index'), 'Send a text' => null]" />

    @unless ($connected)
        <x-ui.alert variant="warning" title="Text messages cannot be sent yet">
            The SMS provider is not connected. Ask the person who manages your server to connect it.
        </x-ui.alert>
    @endunless

    @unless ($smsOn)
        <x-ui.alert variant="warning" title="SMS is off">
            The message will not be sent. It will be listed in the SMS log as Skipped until an administrator turns SMS on.
            @can('manage-settings')
                <x-slot:actions><x-ui.button size="sm" variant="secondary" :href="route('admin.settings.edit', 'sms')">Open SMS settings</x-ui.button></x-slot:actions>
            @endcan
        </x-ui.alert>
    @endunless

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('sms.send') }}">
                @csrf
                <x-ui.card>
                    <div class="row g-3">
                        <x-ui.input wrapper-class="col-12 col-md-6" name="recipient_number" type="tel" label="Mobile number" required
                            placeholder="09171234567" maxlength="16" inputmode="tel" autocomplete="off"
                            help="A Philippine mobile number, for example 09171234567 or +639171234567." />
                        <x-ui.input wrapper-class="col-12 col-md-6" name="recipient_name" label="Recipient name" optional
                            placeholder="Juan Dela Cruz" maxlength="150" autocomplete="off" help="Helps you find the message in the SMS log." />
                        <div class="col-12">
                            <x-ui.textarea name="message" id="msgInput" label="Message" rows="4" maxlength="160" required
                                placeholder="Type your message" />
                            <p class="sms-counter mt-1 mb-0" id="charCount" aria-live="polite"></p>
                        </div>
                    </div>
                    <x-slot:footer>
                        <x-ui.button variant="secondary" :href="route('sms.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="send" :disabled="! $connected">Send text</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>
        </div>

        <div class="col-lg-4">
            <x-ui.card title="Good to know">
                <x-ui.description-list layout="stacked">
                    <x-ui.description-item label="Sent as" empty="Not set">{{ $sender }}</x-ui.description-item>
                    <x-ui.description-item label="Length">Up to 160 characters, which is 1 text message.</x-ui.description-item>
                    <x-ui.description-item label="Cost">Each text message uses 1 SMS credit.</x-ui.description-item>
                    <x-ui.description-item label="Numbers">Philippine mobile numbers only.</x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('msgInput');
    var count = document.getElementById('charCount');
    if (!input || !count) return;
    function update() {
        var len = input.value.length;
        count.textContent = len + ' of 160 characters' + (len ? ', 1 text message' : '');
        count.classList.toggle('is-warning', len > 150);
    }
    input.addEventListener('input', update);
    update();
});
</script>
@endpush
