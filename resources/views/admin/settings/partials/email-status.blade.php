{{-- Email status card: whether outgoing email works, in plain words (App\Support\MailHealth). Technical details collapsed. --}}
@php
    $logMode  = (bool) $status['log_mode'];
    $ready    = (bool) ($status['ready'] ?? ! $logMode);
    $problems = $status['problems'] ?? [];
    $failures = $status['failures'] ?? [];
    $warnings = collect($problems)->where('level', 'warning');
@endphp
<x-ui.card class="status-card">
    <div class="status-card-main">
        <div class="min-w-0">
            <p class="status-card-title">
                <span @class(['status-dot', 'is-on' => $ready && $warnings->isEmpty(), 'is-warning' => ! $ready || $warnings->isNotEmpty()])></span>
                @if ($logMode)
                    Email sending is not set up yet
                @elseif (! $ready)
                    Email cannot be sent
                @else
                    Email is set up
                @endif
            </p>
            <p class="text-ink-2 mb-0">
                @if ($logMode)
                    Messages are saved to the system log instead. Password reset links, welcome emails and appointment emails do not reach anyone until
                    the person who manages your server sets up outgoing email.
                @elseif (! $ready)
                    Password reset links, welcome emails, sign-in codes and appointment emails fail until this is fixed on the server.
                @else
                    Emails are sent from {{ $status['from_name'] }} ({{ $status['from_address'] }}).
                @endif
            </p>
            @if (! $logMode && $problems !== [])
                <ul class="mt-2 mb-0 ps-3 text-ink-2">
                    @foreach ($problems as $problem)
                        <li @class(['text-danger' => $problem['level'] === 'error'])>{{ $problem['text'] }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($failures !== [])
                <p class="mt-2 mb-1 fw-semibold">Latest email failures</p>
                <ul class="mb-0 ps-3 text-ink-2 small">
                    @foreach ($failures as $failure)
                        <li><span class="tabular">{{ $failure['time'] }}</span>: {{ $failure['text'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <details class="tech-details">
        <summary>Technical details</summary>
        <ul>
            <li>Sending method: <code>{{ $status['mailer'] }}</code>@if ($status['host']), mail server <code>{{ $status['host'] }}</code>@endif.</li>
            @if ($logMode)
                <li>To send real email, the server needs <code>MAIL_MAILER=brevo</code> and <code>BREVO_API_KEY</code>, or <code>MAIL_MAILER=smtp</code> with <code>MAIL_HOST</code>, <code>MAIL_PORT</code>, <code>MAIL_USERNAME</code> and <code>MAIL_PASSWORD</code>.</li>
                @if ($status['log_hidden'])
                    <li>The system log only keeps messages at level <code>{{ $status['log_level'] }}</code> and above, so these emails are not even written to the log (they are logged at <code>debug</code> level).</li>
                @endif
            @endif
            <li>Sent from: {{ $status['from_name'] }} &lt;{{ $status['from_address'] }}&gt;. Leave the fields below empty to use the server settings <code>MAIL_FROM_NAME</code> and <code>MAIL_FROM_ADDRESS</code>.</li>
            <li>Emails are sent right away, not through the background queue.</li>
            @foreach ($status['switches'] ?? [] as $switch)
                <li>{{ $switch }}.</li>
            @endforeach
            <li>On the server, <code>php artisan mail:check</code> shows the same checks and the latest failures.</li>
        </ul>
    </details>
</x-ui.card>
