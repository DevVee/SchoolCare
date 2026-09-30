{{-- Security status card: whether sign-in codes are asked for, and whether email can deliver them. --}}
@php
    $on = (bool) $status['otp_on'];
    $emailReady = (bool) $status['email_ready'];
    $who = $status['applies_to'] === 'admins' ? 'administrators' : 'everyone';
@endphp
<x-ui.card class="status-card">
    <div class="status-card-main">
        <div class="min-w-0">
            <p class="status-card-title">
                <span @class(['status-dot', 'is-on' => $on && $emailReady, 'is-warning' => ! $emailReady])></span>
                @if ($on && $emailReady)
                    Sign-in codes are on
                @elseif ($on)
                    Sign-in codes are paused
                @elseif ($emailReady)
                    Sign-in codes are off
                @else
                    Email is not set up, so sign-in codes cannot be turned on
                @endif
            </p>
            <p class="text-ink-2 mb-0">
                @if ($on && $emailReady)
                    After the password, {{ $who }} type a 6-digit code sent to their email.
                @elseif ($on)
                    Email sending is not set up on the server, so no code is asked for until it is. Nobody is locked out.
                @elseif ($emailReady)
                    Before turning this on, send yourself a test email from Email settings and make sure it arrives.
                @else
                    Codes could not be delivered, and people would be locked out. Set up email first, then send yourself a test email from Email settings.
                @endif
            </p>
        </div>
        @unless ($on && $emailReady)
            <x-ui.button variant="secondary" size="sm" icon="envelope" :href="route('admin.settings.edit', 'email')">Email settings</x-ui.button>
        @endunless
    </div>

    <details class="tech-details">
        <summary>Technical details</summary>
        <ul>
            <li>Sending method: <code>{{ $status['mailer'] }}</code>.@unless ($emailReady) Codes need a real sending method, for example <code>MAIL_MAILER=brevo</code> with <code>BREVO_API_KEY</code>.@endunless</li>
            <li>A code works once and expires in 10 minutes. After 5 wrong codes it stops working and a new one must be sent. A new code can be sent once a minute, up to 5 times an hour.</li>
            <li>If a code email cannot be sent, the person stays signed out and sees a message. The error is written to the system log.</li>
            <li>Remembered devices are forgotten when the person changes their password. An administrator can also forget them from the user's page.</li>
            <li>In an emergency, the person who manages the server can turn sign-in codes off with <code>php artisan auth:otp-off</code>.</li>
        </ul>
    </details>
</x-ui.card>
