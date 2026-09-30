{{--
    Weekly opening hours as a simple per-day grid (Open switch + opening and closing time).
    The grid writes the stored text ("monday | 07:30-17:00", "sunday | closed") into the hidden
    clinic_weekly_hours field, so the saved format and ClinicHours stay the same.
    Expects: $key, $def, $settings.
--}}
@php
    $days = \App\Support\ClinicHours::DAYS;
    $text = old($key, $settings->toText($key));
    $current = $settings->toOptions((string) $text);
    $rows = [];
    foreach ($days as $day) {
        $raw = strtolower(trim((string) ($current[$day] ?? '')));
        $open = preg_match('/^(\d{1,2}):(\d{2})\s*(?:-|to)\s*(\d{1,2}):(\d{2})$/', $raw, $m) === 1;
        $rows[$day] = [
            'open' => $open,
            'from' => $open ? sprintf('%02d:%02d', $m[1], $m[2]) : '07:30',
            'to' => $open ? sprintf('%02d:%02d', $m[3], $m[4]) : '17:00',
        ];
    }
@endphp
<div class="col-12">
    <x-ui.field label="Opening hours" :name="$key" for="hours-monday-open"
        help="Appointments can only be booked on open days, and the public schedule shows these hours.">
        <input type="hidden" name="{{ $key }}" id="setting_{{ $key }}" value="{{ $text }}" data-hours-value>
        <div class="hours-grid" data-hours-grid>
            @foreach ($rows as $day => $r)
                <div class="hours-row" data-day="{{ $day }}">
                    <div class="form-check form-switch c-check hours-day">
                        <input class="form-check-input" type="checkbox" role="switch" id="hours-{{ $day }}-open" data-hours-open @checked($r['open'])>
                        <label class="form-check-label" for="hours-{{ $day }}-open">{{ ucfirst($day) }}</label>
                    </div>
                    <div class="hours-times" @if (! $r['open']) hidden @endif>
                        <label class="visually-hidden" for="hours-{{ $day }}-from">{{ ucfirst($day) }} opens at</label>
                        <input type="time" class="form-control form-control-sm" id="hours-{{ $day }}-from" value="{{ $r['from'] }}" data-hours-from>
                        <span class="text-muted">to</span>
                        <label class="visually-hidden" for="hours-{{ $day }}-to">{{ ucfirst($day) }} closes at</label>
                        <input type="time" class="form-control form-control-sm" id="hours-{{ $day }}-to" value="{{ $r['to'] }}" data-hours-to>
                    </div>
                    <span class="hours-closed text-muted" @if ($r['open']) hidden @endif>Closed</span>
                    <span class="hours-error" hidden>Closing time must be after opening time.</span>
                </div>
            @endforeach
        </div>
    </x-ui.field>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var grid = document.querySelector('[data-hours-grid]');
    var out = document.querySelector('[data-hours-value]');
    if (!grid || !out) return;
    function sync() {
        var lines = [];
        grid.querySelectorAll('.hours-row').forEach(function (row) {
            var open = row.querySelector('[data-hours-open]').checked;
            var from = row.querySelector('[data-hours-from]').value;
            var to = row.querySelector('[data-hours-to]').value;
            var bad = open && from && to && to <= from;
            row.querySelector('.hours-times').hidden = !open;
            row.querySelector('.hours-closed').hidden = open;
            row.querySelector('.hours-error').hidden = !bad;
            lines.push(row.dataset.day + ' | ' + (open && from && to ? from + '-' + to : 'closed'));
        });
        out.value = lines.join('\n');
    }
    grid.addEventListener('change', sync);
    grid.addEventListener('input', sync);
});
</script>
@endpush
