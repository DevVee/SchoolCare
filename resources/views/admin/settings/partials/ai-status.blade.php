{{-- AI assistant status: on/off and whether the AI service is connected, in plain words. --}}
@php
    $aiOn = (bool) settings('ai_enabled', true);
    $connected = (bool) $status['api_key_configured'];
    $assistant = settings('ai_assistant_name') ?: 'The assistant';
@endphp
<x-ui.card class="status-card">
    <div class="status-card-main">
        <div class="min-w-0">
            <p class="status-card-title">
                <span @class(['status-dot', 'is-on' => $aiOn && $connected, 'is-warning' => $aiOn && ! $connected])></span>
                @if (! $aiOn)
                    {{ $assistant }} is turned off
                @elseif ($connected)
                    {{ $assistant }} is on
                @else
                    {{ $assistant }} is on, but not connected
                @endif
            </p>
            <p class="text-ink-2 mb-0">
                @if (! $aiOn)
                    Staff do not see the assistant in the menu.
                @elseif ($connected)
                    Staff can ask quick questions about the system, clinic work and health topics.
                @else
                    It cannot answer until the AI service is connected. Ask the person who manages your server to connect it.
                @endif
            </p>
        </div>
    </div>
    <details class="tech-details">
        <summary>Technical details</summary>
        <ul>
            <li>Answers come from Groq, an online AI service. Questions are sent to it over a secure connection.</li>
            <li>The Groq access key is kept on the server in the setting <code>GROQ_API_KEY</code>. It is {{ $connected ? 'set' : 'not set' }}, and it is never shown here.</li>
        </ul>
    </details>
</x-ui.card>
