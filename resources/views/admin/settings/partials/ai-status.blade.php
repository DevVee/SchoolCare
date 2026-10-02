{{-- AI assistant status: on/off and whether the AI service is connected, in plain words. --}}
@php
    $aiOn = (bool) settings('ai_enabled', true);
    $connected = (bool) $status['api_key_configured'];
    $assistant = settings('ai_assistant_name') ?: 'The assistant';
    $webSearch = (bool) ($status['web_search'] ?? false);
    $modelLabel = settings()->definition('ai_model')['options'][$status['model'] ?? ''] ?? ($status['model'] ?? '');
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
                    Staff can ask it anything: how to use the system, writing letters and notices, health topics and more.
                    @if ($webSearch)
                        Questions about recent events may be searched on the web.
                    @endif
                @else
                    It cannot answer until it is connected. Paste a Groq API key under Connection below and save.
                @endif
            </p>
        </div>
    </div>
    <details class="tech-details">
        <summary>Technical details</summary>
        <ul>
            <li>Answers come from Groq, an online AI service. Questions are sent to it over a secure connection.</li>
            <li>The dashboard brief (a few lines about today) is also written by Groq, from counts only.</li>
            @if ($modelLabel !== '')
                <li>Model in use: {{ $modelLabel }}.</li>
            @endif
            <li>
                @if ($webSearch)
                    Web search is on. When a question needs recent information, the search words are sent to Groq's web search.
                @else
                    Web search is off{{ settings('ai_web_search') ? ' because the chosen model cannot search' : '' }}.
                @endif
            </li>
            <li>With each question, the assistant also gets today's date, the asking user's first name and role, and today's clinic numbers (appointment and visit counts, low stock and expiring medicines). No patient names or details are included in these.</li>
            <li>
                @if (settings('ai_read_patients', true))
                    Patient records: when someone asks about a patient, the assistant may look up the name, patient number, course or grade and section, the last 3 clinic visits (date, complaint, action taken), allergies and existing conditions, and send them to Groq. It can also see medicine stock and the appointment schedule, within the asking user's permissions. Every lookup is recorded in the audit logs.
                @else
                    Patient records: off. No patient names or details are sent to Groq. The assistant can still see medicine stock and free appointment times, within the asking user's permissions.
                @endif
            </li>
            <li>
                @if (settings('ai_actions_enabled', false))
                    Actions: on. The assistant can prepare a text message, email, appointment or settings change as a card. It happens only when the person chatting taps Confirm, and each one is recorded in the audit logs. The text of a message it prepares is written by Groq.
                @else
                    Actions: off. The assistant cannot prepare messages, appointments or settings changes.
                @endif
            </li>
            <li>
                @if (($status['api_key_source'] ?? null) === 'settings')
                    The Groq API key is saved here, encrypted. It is never shown, only its last 4 characters.
                @elseif (($status['api_key_source'] ?? null) === 'server')
                    The Groq API key comes from the server setting <code>GROQ_API_KEY</code>. A key saved here would be used instead.
                @else
                    No Groq API key is set, here or on the server (<code>GROQ_API_KEY</code>).
                @endif
            </li>
        </ul>
    </details>
</x-ui.card>
