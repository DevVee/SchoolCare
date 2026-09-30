@extends('layouts.app')

@section('title', 'Low stock')

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Low stock'"
        description="Active medicines at or below their low stock alert level. Restock them with stock in."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => route('medicines.index'), 'Low stock' => null]">
        <x-slot:actions>
            @can('manage-inventory')
                <x-ui.button :href="route('inventory.stock-in.form')" icon="box-arrow-in-down">Stock in</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('medicines.partials.module-tabs', ['active' => 'low-stock'])

    <x-ui.card flush>
        <x-ui.table responsive="stack" :paginator="$medicines" noun="medicines" caption="Low stock medicines">
            <x-slot:head>
                <x-ui.th>Medicine</x-ui.th>
                <x-ui.th priority="md">Category</x-ui.th>
                <x-ui.th align="end">On hand</x-ui.th>
                <x-ui.th align="end" priority="md">Alert at</x-ui.th>
                <x-ui.th>Stock</x-ui.th>
                <x-ui.th priority="lg">Expiry</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($medicines as $med)
                <tr>
                    <x-ui.td identity>
                        <a href="{{ route('medicines.show', $med) }}" class="cell-title d-block">{{ $med->name }}</a>
                        @if ($med->generic_name || $med->batch_number)
                            <span class="cell-sub d-block">{{ $med->generic_name ?: 'Lot '.$med->batch_number }}</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td priority="md" label="Category">
                        @if ($med->category)
                            <x-ui.badge color="neutral" :dot="false" size="sm">{{ $med->category->name }}</x-ui.badge>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td numeric label="On hand">@include('medicines.partials.qty', ['qty' => $med->quantity, 'unit' => $med->unit])</x-ui.td>
                    <x-ui.td numeric priority="md" label="Alert at" muted>{{ number_format($med->low_stock_threshold) }}</x-ui.td>
                    <x-ui.td label="Stock">@include('medicines.partials.stock-status', ['medicine' => $med])</x-ui.td>
                    <x-ui.td priority="lg" label="Expiry">@include('medicines.partials.expiry', ['date' => $med->expiration_date])</x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$med->name">
                            <x-ui.action-menu.item :href="route('medicines.show', $med)" icon="eye">View</x-ui.action-menu.item>
                            @can('manage-inventory')
                                <x-ui.action-menu.item :href="route('inventory.stock-in.form', ['medicine_id' => $med->id])" icon="box-arrow-in-down">Stock in</x-ui.action-menu.item>
                            @endcan
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                <x-ui.empty-state icon="shield-check" tone="success" title="All medicines are well stocked" description="No active medicine is at or below its low stock alert level." compact />
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
