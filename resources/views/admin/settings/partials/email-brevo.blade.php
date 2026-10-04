{{-- What Brevo itself says (App\Support\BrevoStatus), in plain words: one headline, a short checklist,
     the fix steps only when something is wrong, the last test email, and recent emails one row each. --}}
@php
    $brevo  = $status['brevo'] ?? null;
    $test   = $status['test'] ?? null;
    $domain = $brevo['domain'] ?? null;
    $sender = $brevo['sender'] ?? null;
    $kinds  = collect($brevo['problems'] ?? [])->keyBy('kind');
    $lastBad = collect($brevo['emails'] ?? [])->first()['bad'] ?? false;

    // Headline: the most important thing first.
    [$tone, $icon, $headline, $line] = match (true) {
        $brevo === null => [null, null, null, null],
        ! $brevo['reachable'] => ['warning', 'exclamation-circle-fill', 'Could not reach Brevo', 'Brevo did not answer the server. Press Check again in a minute.'],
        ! $brevo['key_ok'] => ['danger', 'x-circle-fill', 'Brevo does not accept the API key', $kinds['key']['text'] ?? ''],
        $kinds->has('sender') => ['danger', 'x-circle-fill', 'Brevo is rejecting your emails', $kinds['sender']['text']],
        $kinds->has('domain') => ['warning', 'exclamation-circle-fill', 'Emails may land in spam', $kinds['domain']['text']],
        $lastBad => ['warning', 'exclamation-circle-fill', 'The last email was not delivered', $kinds['event']['text'] ?? ''],
        default => ['success', 'check-circle-fill', 'Brevo is delivering your emails', 'The API key works and '.($domain['name'] ?? 'your domain').' is authenticated.'],
    };

    $checks = $brevo && $brevo['key_ok'] ? array_filter([
        ['ok' => true, 'text' => 'API key accepted'.($brevo['account'] ? ' ('.$brevo['account'].')' : '')],
        $domain ? ['ok' => $domain['authenticated'], 'text' => $domain['name'].($domain['authenticated'] ? ' is authenticated' : ($domain['exists'] ? ' is not authenticated yet' : ' is not added in Brevo'))] : null,
        $sender ? ['ok' => $sender['verified'], 'text' => $sender['email'].($sender['verified'] ? ' is allowed to send' : ' is not allowed to send')] : null,
    ]) : [];

    $missing = collect($domain['records'] ?? [])->where('ok', false);
    $resultTone = fn (array $e) => $e['bad'] ? 'danger' : (in_array($e['event'], ['delivered', 'opened'], true) ? 'success' : 'neutral');
@endphp
@if ($brevo !== null)
    <x-ui.card title="Brevo delivery" subtitle="What Brevo, the email service, did with your emails." flush>
        <x-slot:actions>
            <x-ui.button variant="secondary" size="sm" icon="arrow-clockwise" :href="route('admin.settings.edit', ['group' => 'email', 'recheck' => 1])">Check again</x-ui.button>
        </x-slot:actions>

        <div class="vstack gap-3 p-3">
            {{-- Headline --}}
            <div class="d-flex align-items-start gap-2">
                <x-ui.icon :name="$icon" :class="\Illuminate\Support\Arr::toCssClasses(['fs-5 mt-1', 'text-success' => $tone === 'success', 'text-warning' => $tone === 'warning', 'text-danger' => $tone === 'danger'])" />
                <div class="min-w-0">
                    <p class="fw-semibold mb-0">{{ $headline }}</p>
                    @if ($line)
                        <p class="text-ink-2 mb-0">{{ $line }}</p>
                    @endif
                </div>
            </div>

            {{-- Checklist --}}
            @if ($checks !== [])
                <ul class="list-unstyled vstack gap-1 mb-0">
                    @foreach ($checks as $check)
                        <li class="d-flex align-items-center gap-2">
                            <x-ui.icon :name="$check['ok'] ? 'check-circle-fill' : 'x-circle-fill'" :class="$check['ok'] ? 'text-success' : 'text-danger'" />
                            <span>{{ $check['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- How to fix: only while the domain is not authenticated --}}
            @if ($domain && ! $domain['authenticated'] && $domain['records'] !== [])
                <div class="border rounded-3 p-3">
                    <p class="fw-semibold mb-2">How to fix it</p>
                    <ol class="ps-3 mb-3 text-ink-2">
                        <li>At your domain host (Hostinger: Domains &gt; {{ $domain['name'] }} &gt; DNS / Nameservers), add the {{ $missing->count() === 1 ? 'record' : $missing->count().' records' }} marked Missing below. If a record with the same name already exists (such as <code>_dmarc</code>), edit it instead of adding a second one.</li>
                        <li>In Brevo, open Senders, Domains &amp; Dedicated IPs &gt; Domains &gt; {{ $domain['name'] }} and press Authenticate.</li>
                        <li>Come back here and press Check again.</li>
                    </ol>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Type</th><th>Name</th><th>Value</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($domain['records'] as $record)
                                    <tr>
                                        <td><code>{{ $record['type'] }}</code></td>
                                        <td><code class="user-select-all">{{ $record['host'] }}</code></td>
                                        <td class="text-break"><code class="user-select-all">{{ $record['value'] }}</code></td>
                                        <td class="text-end"><x-ui.badge :color="$record['ok'] ? 'success' : 'warning'" size="sm">{{ $record['ok'] ? 'Found' : 'Missing' }}</x-ui.badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- The last test email, followed through Brevo --}}
            @if ($test)
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-semibold">Test email to {{ $test['to'] }}</span>
                    <span class="text-muted fs-sm tabular">{{ $test['at'] }}</span>
                    @if ($test['final'])
                        <x-ui.badge :color="$resultTone($test['final'])">{{ $test['final']['label'] }}</x-ui.badge>
                    @else
                        <x-ui.badge color="neutral">Waiting for Brevo</x-ui.badge>
                    @endif
                </div>
                @if ($test['final'] && $test['final']['reason'] !== '')
                    <p class="text-danger fs-sm mb-0 mt-n2">{{ $test['final']['reason'] }}</p>
                @elseif ($test['final'] && $test['final']['event'] === 'delivered')
                    <p class="text-ink-2 fs-sm mb-0 mt-n2">Brevo handed it to the receiving mail server. Not in the inbox? Look in Spam or Promotions.</p>
                @elseif (! $test['final'] || $test['final']['event'] === 'requests')
                    <p class="text-ink-2 fs-sm mb-0 mt-n2">Press Check again in a minute.</p>
                @endif
            @endif
        </div>

        {{-- Recent emails: one row per email --}}
        @if ($brevo['key_ok'])
            <div class="border-top">
                <p class="fw-semibold mb-0 px-3 pt-3">Recent emails</p>
                @if ($brevo['emails'] === [])
                    <p class="text-ink-2 px-3 pb-3 mb-0">No emails in the last 30 days.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th class="ps-3">When</th><th>To</th><th>Subject</th><th class="pe-3">Result</th></tr></thead>
                            <tbody>
                                @foreach (array_slice($brevo['emails'], 0, 8) as $email)
                                    <tr>
                                        <td class="ps-3 text-nowrap tabular text-muted fs-sm">{{ $email['time'] }}</td>
                                        <td class="text-nowrap">{{ $email['email'] }}</td>
                                        <td class="text-break">{{ $email['subject'] }}</td>
                                        <td class="pe-3">
                                            <x-ui.badge :color="$resultTone($email)" size="sm">{{ $email['label'] }}</x-ui.badge>
                                            @if ($email['bad'] && $email['reason'] !== '')
                                                <div class="text-muted fs-sm mt-1">{{ $email['reason'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:footer>
            <span class="me-auto text-muted fs-sm">Checked {{ $brevo['checked_at'] }}. Full log in Brevo: Transactional &gt; Logs.</span>
        </x-slot:footer>
    </x-ui.card>
@endif
