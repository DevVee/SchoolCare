{{--
    x-ui.description-list: label/value rows for show pages.
    <x-ui.description-list :items="[
        'Patient no.' => $patient->patient_number,
        'Birthdate'   => $patient->birthdate?->format('M d, Y'),
        'Contact'     => $patient->contact_number,
    ]" />

    Custom values (HTML, badges) with child rows:
    <x-ui.description-list>
        <x-ui.description-item label="Status"><x-ui.status-badge :status="$patient->is_active" type="patient" /></x-ui.description-item>
        <x-ui.description-item label="Allergies">{{ $patient->allergies }}</x-ui.description-item>
    </x-ui.description-list>

    Empty values (null / "") show the `empty` text. layout: grid (default) | stacked | compact
--}}
@props([
    'items' => [],
    'empty' => 'Not recorded',
    'layout' => 'grid',
])
<dl {{ $attributes->class(['c-dl', 'c-dl-stacked' => $layout === 'stacked', 'c-dl-compact' => $layout === 'compact']) }}>
    @foreach ($items as $itemLabel => $itemValue)
        <dt>{{ $itemLabel }}</dt>
        <dd>
            @if ($itemValue === null || $itemValue === '' || $itemValue === [])
                <span class="dl-empty">{{ $empty }}</span>
            @else
                {{ $itemValue }}
            @endif
        </dd>
    @endforeach
    {{ $slot }}
</dl>
