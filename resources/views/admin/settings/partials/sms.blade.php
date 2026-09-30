{{--
    SMS: sender name and message templates.
    Templates are edited as normal text. Insert buttons add friendly markers such as [Patient name];
    the browser turns them back into the stored {tokens} when the form is saved (without JavaScript the
    raw {tokens} are shown and still work).
--}}
@php
    $templates = collect($fields)->filter(fn ($d) => ! empty($d['placeholders']));
    $globals = config('settings.sms_globals', []);
    $labels = config('settings.sms_placeholder_labels', []);
    $samples = config('settings.sms_samples', []) + [
        'clinic' => settings('clinic_name'),
        'clinic_contact' => settings('clinic_contact') ?: '0917 000 0000',
        'app' => settings('app_name'),
    ];
    $events = config('settings.sms_events', []);
    $eventToggle = collect($events)->mapWithKeys(fn ($e) => [$e['template'] => $e['toggle']]);
    $smsOn = (bool) settings('sms_enabled');
@endphp

<x-ui.section title="Sender name" description="The name people see on their phone instead of a number.">
    <div class="row g-3">
        @include('admin.settings.partials.field', ['key' => 'sms_sender_name', 'def' => $fields['sms_sender_name'], 'col' => 'col-12 col-md-8'])
    </div>
</x-ui.section>

<div class="form-section form-section-full">
    <div class="mb-3">
        <h2 class="form-section-title">Messages</h2>
        <p class="form-section-desc mb-0">
            Edit the words of each message. Use the buttons to add details that are filled in for each patient.
            The preview shows how the text will look with example details. Leave a message empty to use the standard text.
        </p>
    </div>

    <div class="vstack gap-3">
        @foreach ($templates as $key => $def)
            @php
                $value = old($key, (string) $settings->get($key, ''));
                $toggle = $eventToggle[$key] ?? null;
                $eventOn = $toggle ? (bool) settings($toggle) : true;
                $tokens = array_values(array_unique(array_merge($def['placeholders'], $globals)));
                $hasErr = $errors->has($key);
            @endphp
            <div class="sms-template" data-template data-default="{{ $def['default'] ?? '' }}">
                <div class="sms-template-head">
                    <label class="sms-template-label" for="setting_{{ $key }}">{{ $def['label'] }}</label>
                    @if (! $smsOn)
                        <x-ui.badge color="neutral" size="sm">Not sent, SMS is off</x-ui.badge>
                    @elseif ($eventOn)
                        <x-ui.badge color="success" size="sm">Sent automatically</x-ui.badge>
                    @else
                        <x-ui.badge color="neutral" size="sm">Turned off</x-ui.badge>
                    @endif
                </div>
                @if (! empty($def['help']))<p class="text-muted fs-sm mb-2">{{ $def['help'] }}@if ($toggle) <a href="{{ route('admin.settings.edit', 'notifications') }}" class="ms-1">Change in Notifications</a>@endif</p>@endif

                <div class="row g-3">
                    <div class="col-12 col-xl-7">
                        <textarea id="setting_{{ $key }}" name="{{ $key }}" rows="4" data-sms-input
                                  @class(['form-control', 'is-invalid' => $hasErr])
                                  @if ($hasErr) aria-invalid="true" aria-describedby="setting_{{ $key }}-error" @endif
                                  placeholder="Empty: the standard message is sent">{{ $value }}</textarea>
                        <x-ui.field-error :name="$key" :id="'setting_'.$key.'-error'" />
                        <div class="sms-insert" role="group" aria-label="Add a detail to {{ $def['label'] }}">
                            <span class="sms-insert-label">Add:</span>
                            @foreach ($tokens as $token)
                                <button type="button" class="btn btn-secondary btn-xs" data-insert="{{ $token }}">
                                    <x-ui.icon name="plus" />{{ $labels[$token] ?? \Illuminate\Support\Str::headline($token) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 col-xl-5">
                        <div class="sms-preview">
                            <p class="sms-preview-label">Preview <span class="text-muted fw-normal">(example details)</span></p>
                            <div class="sms-bubble" data-preview>{{ $value }}</div>
                            <p class="sms-counter" data-counter></p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var samples = @json($samples);
    var labels = @json($labels);
    var form = document.getElementById('settingsForm');
    var byLabel = {};
    Object.keys(labels).forEach(function (k) { byLabel[labels[k].toLowerCase()] = k; });

    // Characters that fit the standard SMS alphabet; anything else makes each message shorter.
    var gsm = /^[\n\r @£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\\[~\]|€]*$/;

    function toFriendly(text) {
        return text.replace(/\{([a-z_]+)\}/gi, function (m, k) {
            return labels[k.toLowerCase()] ? '[' + labels[k.toLowerCase()] + ']' : m;
        });
    }
    function toTokens(text) {
        return text.replace(/\[([^\]\n]+)\]/g, function (m, l) {
            var k = byLabel[l.trim().toLowerCase()];
            return k ? '{' + k + '}' : m;
        });
    }
    function render(text) {
        return toTokens(text).replace(/\{([a-z_]+)\}/gi, function (m, k) {
            k = k.toLowerCase();
            return Object.prototype.hasOwnProperty.call(samples, k) && samples[k] !== null ? String(samples[k]) : m;
        });
    }

    function update(wrap) {
        var ta = wrap.querySelector('[data-sms-input]');
        var usingDefault = ta.value.trim() === '';
        var text = render(usingDefault ? (wrap.dataset.default || '') : ta.value);
        var special = !gsm.test(text);
        var single = special ? 70 : 160, multi = special ? 67 : 153;
        var len = text.length;
        var parts = len === 0 ? 0 : (len <= single ? 1 : Math.ceil(len / multi));
        var counter = wrap.querySelector('[data-counter]');
        var msg = parts + ' text ' + (parts === 1 ? 'message' : 'messages') + ', ' + len + ' characters';
        if (usingDefault) msg = 'Standard message. ' + msg;
        if (parts > 1) msg += '. Each text message is charged separately.';
        if (special) msg += ' Special characters (such as emoji or curly quotes) make each text message shorter.';
        counter.textContent = msg;
        counter.classList.toggle('is-warning', parts > 1);
        var bubble = wrap.querySelector('[data-preview]');
        bubble.textContent = text;
        bubble.classList.toggle('is-default', usingDefault);
    }

    document.querySelectorAll('[data-template]').forEach(function (wrap) {
        var ta = wrap.querySelector('[data-sms-input]');
        ta.value = toFriendly(ta.value);
        ta.addEventListener('input', function () { update(wrap); });
        wrap.querySelectorAll('[data-insert]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var token = '[' + (labels[btn.dataset.insert] || btn.dataset.insert) + ']';
                var s = ta.selectionStart != null ? ta.selectionStart : ta.value.length;
                var e = ta.selectionEnd != null ? ta.selectionEnd : ta.value.length;
                var before = ta.value.slice(0, s), after = ta.value.slice(e);
                var pad = before && !/\s$/.test(before) ? ' ' : '';
                ta.value = before + pad + token + after;
                ta.focus();
                ta.selectionStart = ta.selectionEnd = s + pad.length + token.length;
                update(wrap);
            });
        });
        update(wrap);
    });

    // Save the stored {tokens}, not the friendly [markers].
    if (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('[data-sms-input]').forEach(function (ta) { ta.value = toTokens(ta.value); });
        });
    }
});
</script>
@endpush
