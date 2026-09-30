{{--
    x-ui.empty-state: compact (32px icon, one-line title, one-line hint, optional action).
    <x-ui.empty-state icon="people" title="No patients yet" description="Add a patient to start logging clinic visits.">
        <x-ui.button :href="route('patients.create')" icon="person-plus" size="sm">Add patient</x-ui.button>
    </x-ui.empty-state>

    Filtered list with no matches:
    <x-ui.empty-state icon="search" title="No patients match these filters" description="Try a different name or clear the filters.">
        <x-ui.button variant="secondary" size="sm" :href="route('patients.index')">Clear filters</x-ui.button>
    </x-ui.empty-state>

    Quiet single line for panels with nothing in them:
    <x-ui.empty-state quiet icon="door-open" title="Nobody is in the clinic right now." />

    tone: brand (default) | neutral | danger (error state) | any tone.
--}}
@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    'tone' => 'brand',
    'module' => null,     // module key: icon in the module tone (icon defaults to the module icon)
    'compact' => false,   // even less padding (inside small cards)
    'quiet' => false,     // single muted line
])
<div {{ $attributes->class(['empty-state', 'empty-state-compact' => $compact, 'empty-state-quiet' => $quiet]) }} role="status">
    @php
        $moduleKey = $module ? (config('ui.module_aliases.'.$module) ?? $module) : null;
        $moduleDef = $moduleKey ? config('ui.module_meta.'.$moduleKey) : null;
        $iconName = ($moduleDef !== null && $icon === 'inbox') ? ($moduleDef['icon'] ?? $icon) : $icon;
        $toneClass = $moduleDef !== null ? 'tone-chip-'.$moduleKey : 'tone-'.config('ui.bs_tone.'.$tone, $tone);
    @endphp
    <div class="empty-icon {{ $toneClass }}"><x-ui.icon :name="$iconName" /></div>
    <p class="empty-title">{{ $title }}</p>
    @if ($description && ! $quiet)
        <p class="empty-desc">{{ $description }}</p>
    @endif
    @if (isset($actions) || trim($slot) !== '')
        <div class="empty-actions">{{ $actions ?? $slot }}</div>
    @endif
</div>
