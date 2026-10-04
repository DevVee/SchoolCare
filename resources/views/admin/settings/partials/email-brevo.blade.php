{{-- What Brevo itself says (App\Support\BrevoStatus): key, domain authentication with the DNS records to add, the last test email and recent deliveries. --}}
@php
    $brevo = $status['brevo'] ?? null;
    $test  = $status['test'] ?? null;
@endphp
@if ($brevo !== null)
    <x-ui.card title="Brevo delivery" subtitle="What Brevo, the email service, says about your emails. Accepted is not the same as delivered: this shows which emails reached the inbox and why others did not." flush>
        <x-slot:actions>
            <x-ui.button variant="secondary" size="sm" icon="arrow-clockwise" :href="route('admin.settings.edit', ['group' => 'email', 'recheck' => 1])">Check again</x-ui.button>
        </x-slot:actions>

        <div class="vstack gap-3 p-3">
            {{-- Account and domain --}}
            <div class="d-flex flex-wrap gap-2">
                @if (! $brevo['reachable'])
                    <x-ui.badge color="warning">Brevo could not be reached</x-ui.badge>
                @elseif (! $brevo['key_ok'])
                    <x-ui.badge color="danger">API key not accepted</x-ui.badge>
                @else
                    <x-ui.badge color="success">API key accepted</x-ui.badge>
                    @if ($brevo['domain'])
                        @if (! $brevo['domain']['exists'])
                            <x-ui.badge color="warning">{{ $brevo['domain']['name'] }} not added in Brevo</x-ui.badge>
                        @elseif ($brevo['domain']['authenticated'])
                            <x-ui.badge color="success">{{ $brevo['domain']['name'] }} authenticated</x-ui.badge>
                        @else
                            <x-ui.badge color="warning">{{ $brevo['domain']['name'] }} not authenticated</x-ui.badge>
                        @endif
                    @endif
                    @if ($brevo['sender'])
                        <x-ui.badge :color="$brevo['sender']['verified'] ? 'success' : 'danger'">
                            {{ $brevo['sender']['email'] }} {{ $brevo['sender']['verified'] ? 'allowed' : 'not verified' }}
                        </x-ui.badge>
                    @endif
                @endif
            </div>

            @if ($brevo['problems'] !== [])
                <ul class="mb-0 ps-3 text-ink-2">
                    @foreach ($brevo['problems'] as $problem)
                        <li @class(['text-danger' => $problem['level'] === 'error'])>{{ $problem['text'] }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- DNS records Brevo wants, with the ones it has not found yet --}}
            @if (($brevo['domain']['records'] ?? []) !== [] && ! ($brevo['domain']['authenticated'] ?? false))
                <div>
                    <p class="fw-semibold mb-1">DNS records to add at your domain host</p>
                    <p class="text-ink-2 fs-sm mb-2">Hostinger: Domains &gt; {{ $brevo['domain']['name'] }} &gt; DNS / Nameservers. Add each missing record exactly as shown, wait a few minutes, then press Authenticate in Brevo (Senders, Domains &amp; Dedicated IPs &gt; Domains).</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Record</th><th>Type</th><th>Name / Host</th><th>Value</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($brevo['domain']['records'] as $record)
                                    <tr>
                                        <td class="text-nowrap">{{ $record['name'] }}</td>
                                        <td><code>{{ $record['type'] }}</code></td>
                                        <td><code class="user-select-all">{{ $record['host'] }}</code></td>
                                        <td class="text-break"><code class="user-select-all">{{ $record['value'] }}</code></td>
                                        <td>
                                            <x-ui.badge :color="$record['ok'] ? 'success' : 'warning'" size="sm">{{ $record['ok'] ? 'Found' : 'Missing' }}</x-ui.badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- The last test email, followed through Brevo --}}
            @if ($test)
                <div>
                    <p class="fw-semibold mb-1">Test email to {{ $test['to'] }} ({{ $test['at'] }})</p>
                    @if ($test['final'])
                        <p @class(['mb-1', 'text-danger' => $test['final']['bad']])>
                            {{ $test['final']['label'] }}@if ($test['final']['reason'] !== ''): {{ $test['final']['reason'] }}@endif
                        </p>
                        @if ($test['final']['event'] === 'delivered')
                            <p class="text-ink-2 fs-sm mb-0">Brevo handed it to the receiving mail server. If it is not in the inbox, look in Spam or Promotions: until the domain is authenticated, many providers file these emails there.</p>
                        @elseif ($test['final']['event'] === 'requests')
                            <p class="text-ink-2 fs-sm mb-0">Brevo has it but has not delivered it yet. Press Check again in a minute.</p>
                        @endif
                    @else
                        <p class="text-ink-2 mb-0">Brevo has not logged it yet. Press Check again in a minute.</p>
                    @endif
                </div>
            @endif

            {{-- Recent emails --}}
            @if ($brevo['key_ok'])
                <div>
                    <p class="fw-semibold mb-1">Recent emails (last 30 days)</p>
                    @if ($brevo['events'] === [])
                        <p class="text-ink-2 mb-0">Brevo has no emails from this account in the last 30 days.</p>
                    @else
                        <ul class="mb-0 ps-3 text-ink-2 small">
                            @foreach (array_slice($brevo['events'], 0, 8) as $event)
                                <li @class(['text-danger' => $event['bad']])>
                                    <span class="tabular">{{ $event['time'] }}</span>: {{ $event['label'] }}, {{ $event['email'] }}@if ($event['subject'] !== '') ("{{ $event['subject'] }}")@endif @if ($event['reason'] !== '')— {{ $event['reason'] }}@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <p class="text-muted fs-sm mb-0">Checked {{ $brevo['checked_at'] }}. Full log in Brevo: Transactional &gt; Logs.</p>
        </div>
    </x-ui.card>
@endif
