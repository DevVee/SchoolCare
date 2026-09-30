@extends('layouts.app')

@section('title', 'Stock ledger')

@php
    $typeOptions = [
        'stock_in' => 'Stock in',
        'stock_out' => 'Stock out',
        'dispensed' => 'Dispensed',
        'adjustment' => 'Adjustment',
        'disposed' => 'Disposed',
    ];
    $hasFilters = $filters['search'] !== '' || $filters['type'] || $filters['dateFrom'] || $filters['dateTo'];
@endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Stock ledger'" description="Every stock movement: received, removed, given out, disposed and corrected."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('inventory.index'), 'Stock ledger' => null]" />

    @include('inventory.partials.module-tabs', ['active' => 'ledger'])

    <x-ui.filters :action="route('inventory.transactions')" search-placeholder="Medicine, batch or notes"
        :labels="['date_from' => 'From', 'date_to' => 'To', 'type' => 'Type']"
        :options="['type' => $typeOptions]">
        <x-slot:inline>
            <x-ui.input name="date_from" type="date" size="sm" aria-label="From date" :value="$filters['dateFrom']" />
            <x-ui.input name="date_to" type="date" size="sm" aria-label="To date" :value="$filters['dateTo']" />
        </x-slot:inline>
        <x-ui.select name="type" label="Type" size="sm" :options="$typeOptions" placeholder="All types" :selected="$filters['type']" />
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table responsive="stack" :paginator="$transactions" noun="movements" caption="Stock movements">
            <x-slot:head>
                <x-ui.th>Date</x-ui.th>
                <x-ui.th>Medicine</x-ui.th>
                <x-ui.th>Type</x-ui.th>
                <x-ui.th align="end">Change</x-ui.th>
                <x-ui.th align="end" priority="md">Stock</x-ui.th>
                <x-ui.th priority="lg">Batch or supplier</x-ui.th>
                <x-ui.th priority="md">By</x-ui.th>
                <x-ui.th priority="xl">Notes</x-ui.th>
            </x-slot:head>

            @foreach ($transactions as $tx)
                <tr>
                    <x-ui.td label="Date">{{ $tx->created_at->format('M j, Y, g:i A') }}</x-ui.td>
                    <x-ui.td identity>
                        @if ($tx->medicine)
                            <a href="{{ route('medicines.show', $tx->medicine_id) }}" class="cell-title">{{ $tx->medicine->name }}</a>
                        @else
                            <span class="text-muted">Removed medicine</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td label="Type"><x-ui.badge :color="$tx->type_badge" size="sm">{{ $typeOptions[$tx->transaction_type] ?? $tx->type_label }}</x-ui.badge></x-ui.td>
                    <x-ui.td numeric label="Change">@include('medicines.partials.qty', ['qty' => $tx->quantity, 'unit' => $tx->medicine?->unit, 'sign' => true])</x-ui.td>
                    <x-ui.td numeric priority="md" label="Stock"><span class="text-muted">{{ number_format($tx->before_quantity) }} to</span> <span class="fw-semibold">{{ number_format($tx->after_quantity) }}</span></x-ui.td>
                    <x-ui.td priority="lg" label="Batch or supplier" truncate>{{ collect([$tx->batch_number, $tx->supplier])->filter()->implode(', ') ?: '-' }}</x-ui.td>
                    <x-ui.td priority="md" label="By" muted>{{ $tx->performedBy?->name ?? 'Deleted user' }}</x-ui.td>
                    <x-ui.td priority="xl" label="Notes" muted truncate>{{ $tx->notes ?: '-' }}</x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($hasFilters)
                    <x-ui.empty-state icon="search" title="No movements match these filters" description="Try a different date range or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('inventory.transactions')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="journal" title="No stock movements yet" description="Stock in, stock out and dispensing are recorded here." compact />
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
