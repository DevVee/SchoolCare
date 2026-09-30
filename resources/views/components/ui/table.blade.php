{{--
    x-ui.table: responsive table wrapper.
    <x-ui.table responsive="stack" :paginator="$patients" noun="patients">
        <x-slot:toolbar> (optional strip above the table: bulk actions, search) </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th sortable="last_name">Name</x-ui.th>
            <x-ui.th priority="lg">Category</x-ui.th>
            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
        </x-slot:head>
        @foreach ($patients as $p)
            <tr>
                <x-ui.td identity>{{ $p->full_name }}</x-ui.td>
                <x-ui.td label="Category" priority="lg">{{ $p->category }}</x-ui.td>
                <x-ui.td actions> <x-ui.action-menu>...</x-ui.action-menu> </x-ui.td>
            </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state icon="people" title="No patients yet" compact />
        </x-slot:empty>
    </x-ui.table>

    responsive: scroll (default; horizontal scroll inside the wrapper) | stack (rows become cards below `stack-at`)
    The `empty` slot renders (full-width row) when the default slot has no rows.
    Pass :paginator to render the "Showing X to Y of Z" footer + page links.
--}}
@props([
    'responsive' => 'scroll',  // scroll|stack
    'stackAt' => 'sm',         // sm|md : breakpoint BELOW which stacked cards are used
    'dense' => false,
    'striped' => false,
    'hover' => true,
    'sticky' => false,         // pin the first column while scrolling horizontally
    'minWidth' => null,        // e.g. "880px": force horizontal scroll instead of squashing
    'maxHeight' => null,       // e.g. "480px": vertical scroll with sticky header
    'caption' => null,         // accessible caption (visually hidden)
    'columns' => null,         // colspan for the empty row (defaults to all)
    'paginator' => null,
    'noun' => null,            // "patients" in "Showing 1 to 20 of 134 patients"
    'tableClass' => null,
])
@php
    $stack = $responsive === 'stack';
    $rowsEmpty = trim($slot) === '';
    $shellStyle = $maxHeight ? 'max-height: '.$maxHeight : null;
@endphp
<div {{ $attributes->class(['c-table']) }}>
    @isset($toolbar)
        <div {{ $toolbar->attributes->class('table-toolbar') }}>{{ $toolbar }}</div>
    @endisset
    <div @class(['table-responsive', 'table-shell', 'table-sticky-first' => $sticky, 'has-max-height' => $maxHeight]) @if ($shellStyle) style="{{ $shellStyle }}" @endif>
        <table @class([
            'table', 'table-c', 'align-middle', 'mb-0',
            'table-hover' => $hover && ! $rowsEmpty,
            'table-sm' => $dense,
            'table-striped' => $striped,
            'table-stack-'.$stackAt => $stack,
            $tableClass,
        ]) @if ($minWidth) style="min-width: {{ $minWidth }}" @endif>
            @if ($caption)<caption class="visually-hidden">{{ $caption }}</caption>@endif
            @isset($head)
                <thead><tr>{{ $head }}</tr></thead>
            @endisset
            <tbody>
                @if ($rowsEmpty && isset($empty))
                    <tr class="table-empty-row"><td class="table-empty-cell" colspan="{{ $columns ?? 100 }}">{{ $empty }}</td></tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
            @isset($foot)
                <tfoot>{{ $foot }}</tfoot>
            @endisset
        </table>
    </div>
    @if (isset($footer) || ($paginator && ! $rowsEmpty))
        <div class="table-footer">
            @isset($footer)
                {{ $footer }}
            @else
                <x-ui.pagination :paginator="$paginator" :noun="$noun" />
            @endisset
        </div>
    @endif
</div>
