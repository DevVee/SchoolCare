{{--
    x-ui.description-item: one row inside x-ui.description-list.
    <x-ui.description-item label="Guardian">{{ $patient->guardian_name }}</x-ui.description-item>
--}}
@props([
    'label',
    'empty' => 'Not recorded',
])
<dt>{{ $label }}</dt>
<dd {{ $attributes }}>
    @if (trim($slot) === '')
        <span class="dl-empty">{{ $empty }}</span>
    @else
        {{ $slot }}
    @endif
</dd>
