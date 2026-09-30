{{-- SMS status card: on/off switch (saves notifications.sms_enabled), sender name and provider connection in plain words. --}}
@php
    $smsOn = (bool) $status['sms_enabled'];
    $connected = (bool) $status['api_key_configured'];
    $sender = trim((string) settings('sms_sender_name', '')) !== '' ? trim((string) settings('sms_sender_name')) : $status['env_sender'];
    $queue = $status['queue'];
@endphp
<x-ui.card class="status-card">
    <div class="status-card-main">
        <div class="min-w-0">
            <p class="status-card-title">
                <span @class(['status-dot', 'is-on' => $smsOn])></span>
                {{ $smsOn ? 'SMS is on' : 'SMS is off' }}
            </p>
            <p class="text-ink-2 mb-0">
                @if ($smsOn)
                    Text messages are sent automatically for the events turned on in
                    <a href="{{ route('admin.settings.edit', 'notifications') }}">Notifications</a>.
                @else
                    No text messages are sent. Attempts are listed in the SMS log as Skipped. Turn it on when you are ready.
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('admin.settings.update', 'notifications') }}" class="status-card-toggle" id="smsMasterForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="only[]" value="sms_enabled">
            <input type="hidden" name="return_to" value="sms">
            <x-ui.switch name="sms_enabled" id="smsMasterSwitch" :label="$smsOn ? 'On' : 'Turn on'" :checked="$smsOn" data-autosave />
            <noscript><x-ui.button type="submit" size="sm" variant="secondary">Save</x-ui.button></noscript>
        </form>
    </div>

    <dl class="status-facts">
        <div>
            <dt>Sender name</dt>
            <dd>{{ $sender !== '' ? $sender : 'Not set' }}</dd>
        </div>
        <div>
            <dt>SMS provider</dt>
            <dd>
                @if ($connected)
                    <x-ui.badge color="success">Connected</x-ui.badge>
                @else
                    <x-ui.badge color="warning">Not connected</x-ui.badge>
                @endif
            </dd>
        </div>
    </dl>

    @unless ($connected)
        <x-ui.alert variant="warning" class="mt-3">
            The SMS provider is not connected yet, so text messages cannot be delivered. Ask the person who manages your server to connect it.
        </x-ui.alert>
    @endunless

    <details class="tech-details">
        <summary>Technical details</summary>
        <ul>
            <li>Text messages are delivered by Semaphore (semaphore.co), a Philippine SMS service.</li>
            <li>The Semaphore access key is kept on the server in the setting <code>SEMAPHORE_API_KEY</code>. It is {{ $connected ? 'set' : 'not set' }}, and it is never shown here.</li>
            <li>If the sender name above is left empty, the server setting <code>SEMAPHORE_SENDER_NAME</code> is used ({{ $status['env_sender'] !== '' ? $status['env_sender'] : 'not set' }}).</li>
            @if ($queue === 'sync')
                <li>Automatic messages are sent right away, while the page is saving.</li>
            @else
                <li>Automatic messages are sent in the background (queue connection <code>{{ $queue }}</code>). A background worker must be running on the server, otherwise messages wait and are not sent.</li>
            @endif
        </ul>
    </details>
</x-ui.card>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sw = document.getElementById('smsMasterSwitch');
    var form = document.getElementById('smsMasterForm');
    if (!sw || !form) return;
    // Saves as soon as the switch is flipped.
    sw.addEventListener('change', function () {
        if (form.requestSubmit) form.requestSubmit(); else form.submit();
    });
});
</script>
@endpush
