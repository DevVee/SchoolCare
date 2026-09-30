{{--
    x-ui.td: body cell.
    <x-ui.td identity>...</x-ui.td>                      (primary column: card title in stack mode)
    <x-ui.td label="Status">...</x-ui.td>                (label shown before the value in stack mode)
    <x-ui.td label="Category" priority="lg">...</x-ui.td>
    <x-ui.td numeric label="Stock">42</x-ui.td>
    <x-ui.td actions>...</x-ui.td>                       (right-aligned; top-right corner in stack mode)
--}}
@props([
    'label' => null,       // data-label for stacked mode
    'priority' => null,    // sm|md|lg|xl|xxl (match the x-ui.th)
    'align' => null,       // start|center|end
    'identity' => false,
    'actions' => false,
    'numeric' => false,
    'wrap' => false,       // allow wrapping (long text)
    'muted' => false,
    'truncate' => false,   // single line with ellipsis at 240px
])
<td {{ $attributes->class([
    'priority-'.$priority => $priority,
    'text-'.$align => $align,
    'cell-identity' => $identity,
    'cell-actions text-end' => $actions,
    'cell-numeric' => $numeric,
    'cell-wrap' => $wrap,
    'cell-muted' => $muted,
])->merge(array_filter(['data-label' => $label])) }}>@if ($truncate)<span class="cell-truncate" title="{{ trim(strip_tags($slot)) }}">{{ $slot }}</span>@else{{ $slot }}@endif</td>
