{{--
    x-ui.stat-card: summary number card. Uppercase label top-left, 44px tinted icon box
    top-right, big number in the card's tone, optional meta line at the bottom.
    Put several in <x-ui.stat-cards> for a responsive equal-height row.

    <x-ui.stat-card label="Total patients" :value="$total" icon="people" tone="patients" :href="route('patients.index')">
        <span class="stat-mark mark-up">+{{ $newThisMonth }}</span> added this month
    </x-ui.stat-card>

    <x-ui.stat-card label="Staff on duty" value="4" icon="person-badge" tone="teal">
        <span class="stat-meta-item"><span class="stat-dot tone-green"></span>Active 3</span>
        <span class="stat-meta-item"><span class="stat-dot tone-amber"></span>Leave 1</span>
    </x-ui.stat-card>

    tone:  module key (overview, logbook, patients, appointments, consultations, medicines, inventory,
           dispensing, reports, sms, ai, admin, or an alias) or a colour: brand rose amber teal cyan indigo
           sky green emerald violet purple slate (also success warning danger info neutral orange).
           Default brand. Icon defaults to the module icon when tone is a module.
    Slot / meta slot: the bottom line. Markers: .stat-mark + .mark-up|.mark-down|.mark-warn,
           .stat-dot + .tone-{colour}, .stat-meta-item.
    Back-compat: `delta` (+/- sign sets the colour) and `sub` render in the meta line.
--}}
@props([
    'label',
    'value' => null,
    'icon' => null,
    'tone' => 'brand',
    'href' => null,
    'delta' => null,
    'trend' => null,
    'sub' => null,
])
@php
    $raw = strtolower((string) ($tone ?: 'brand'));
    $direct = array_merge(array_keys((array) config('ui.module_meta', [])), [
        'dashboard', 'brand', 'rose', 'amber', 'teal', 'cyan', 'indigo', 'sky', 'green', 'emerald', 'violet', 'purple', 'slate',
        'success', 'warning', 'danger', 'info', 'neutral', 'orange', 'cobi',
    ]);
    $key = in_array($raw, $direct, true) ? $raw : (config('ui.module_aliases.'.$raw) ?? config('ui.bs_tone.'.$raw) ?? 'brand');
    $iconName = $icon ?? config('ui.module_meta.'.$key.'.icon');
    $trendName = $trend;
    if ($trendName === null && $delta !== null && $delta !== '') {
        $d = ltrim((string) $delta);
        $trendName = str_starts_with($d, '-') ? 'down' : (str_starts_with($d, '+') ? 'up' : 'flat');
    }
    $hasMeta = isset($meta) || trim($slot) !== '' || ($delta !== null && $delta !== '') || $sub;
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['stat-tile', 'tone-'.$key]) }}>
    <div class="stat-head">
        <span class="stat-label">{{ $label }}</span>
        @if ($iconName)
            <span class="stat-icon" aria-hidden="true"><x-ui.icon :name="$iconName" /></span>
        @endif
    </div>
    <div class="stat-value">{{ is_numeric($value) ? number_format((float) $value, floor((float) $value) == (float) $value ? 0 : 1) : $value }}</div>
    @if ($hasMeta)
        <div class="stat-meta">
            @if ($delta !== null && $delta !== '')
                <span class="stat-delta trend-{{ $trendName ?? 'flat' }}">{{ $delta }}</span>
            @endif
            @if ($sub)<span>{{ $sub }}</span>@endif
            {{ $meta ?? $slot }}
        </div>
    @endif
</{{ $tag }}>
