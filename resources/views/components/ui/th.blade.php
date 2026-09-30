{{--
    x-ui.th: header cell.
    <x-ui.th>Name</x-ui.th>
    <x-ui.th sortable="created_at">Date</x-ui.th>          (links to ?sort=created_at&dir=asc|desc, keeps other params)
    <x-ui.th priority="lg">Category</x-ui.th>               (hidden below lg; pair with the same priority on x-ui.td)
    <x-ui.th align="end" width="1%">Actions</x-ui.th>

    sortable: column key (string). Current state is read from request('sort') / request('dir').
--}}
@props([
    'sortable' => null,        // column key; true is allowed together with `column`
    'column' => null,
    'priority' => null,        // sm|md|lg|xl|xxl: hide this column below that breakpoint
    'align' => null,           // start|center|end
    'width' => null,
    'sortParam' => 'sort',
    'dirParam' => 'dir',
])
@php
    $key = is_string($sortable) && $sortable !== '' ? $sortable : ($sortable ? $column : null);
    $isSorted = $key && request($sortParam) === $key;
    $dir = strtolower((string) request($dirParam, 'asc')) === 'desc' ? 'desc' : 'asc';
    $nextDir = $isSorted && $dir === 'asc' ? 'desc' : 'asc';
    $url = $key ? request()->fullUrlWithQuery([$sortParam => $key, $dirParam => $nextDir, 'page' => null]) : null;
    $ariaSort = $isSorted ? ($dir === 'asc' ? 'ascending' : 'descending') : ($key ? 'none' : null);
    $icon = $isSorted ? ($dir === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevron-expand';
@endphp
<th scope="col" {{ $attributes->class([
    'priority-'.$priority => $priority,
    'text-'.$align => $align,
    'is-sorted' => $isSorted,
])->merge(array_filter(['aria-sort' => $ariaSort, 'style' => $width ? 'width: '.$width : null])) }}>
    @if ($key)
        <a href="{{ $url }}">{{ $slot }}<x-ui.icon :name="$icon" class="sort-icon" /><span class="visually-hidden">, sort {{ $nextDir === 'asc' ? 'ascending' : 'descending' }}</span></a>
    @else
        {{ $slot }}
    @endif
</th>
