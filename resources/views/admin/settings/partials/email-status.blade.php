{{-- Email status card: whether outgoing email is set up, in plain words. Technical details collapsed. --}}
@php
    $logMode = (bool) $status['log_mode'];
@endphp
<x-ui.card class="status-card">
    <div class="status-card-main">
        <div class="min-w-0">
            <p class="status-card-title">
                <span @class(['status-dot', 'is-on' => ! $logMode, 'is-warning' => $logMode])></span>
                {{ $logMode ? 'Email sending is not set up yet' : 'Email is set up' }}
            </p>
            <p class="text-ink-2 mb-0">
                @if ($logMode)
                    Messages are saved to the system log instead. Password reset links, welcome emails and appointment emails do not reach anyone until
                    the person who manages your server sets up outgoing email.
                @else
                    Emails are sent from {{ $status['from_name'] }} ({{ $status['from_address'] }}).
                @endif
            </p>
        </div>
    </div>

    <details class="tech-details">
        <summary>Technical details</summary>
        <ul>
            <li>Sending method: <code>{{ $status['mailer'] }}</code>@if ($status['host']), mail server <code>{{ $status['host'] }}</code>@endif.</li>
            @if ($logMode)
                <li>To send real email, the server needs these settings: <code>MAIL_MAILER=smtp</code>, <code>MAIL_HOST</code>, <code>MAIL_PORT</code>, <code>MAIL_USERNAME</code> and <code>MAIL_PASSWORD</code>.</li>
                @if ($status['log_hidden'])
                    <li>The system log only keeps messages at level <code>{{ $status['log_level'] }}</code> and above, so these emails are not even written to the log (they are logged at <code>debug</code> level).</li>
                @endif
            @endif
            <li>Sent from: {{ $status['from_name'] }} &lt;{{ $status['from_address'] }}&gt;. Leave the fields below empty to use the server settings <code>MAIL_FROM_NAME</code> and <code>MAIL_FROM_ADDRESS</code>.</li>
            @if ($status['queue'] === 'sync')
                <li>Notification emails are sent right away.</li>
            @else
                <li>Notification emails are sent in the background (queue connection <code>{{ $status['queue'] }}</code>). A background worker must be running on the server, otherwise they wait and are not sent.</li>
            @endif
        </ul>
    </details>
</x-ui.card>
