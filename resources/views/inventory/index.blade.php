@extends('layouts.app')

@section('title', 'Inventory')

@php
    $statusOptions = ['active' => 'Active medicines', 'inactive' => 'Inactive medicines', 'all' => 'All medicines'];
    $categoryOptions = $categories->pluck('name', 'id')->all();
    $hasFilters = $search !== '' || $category || $status !== 'active';
@endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Inventory'" description="Current stock for each medicine. Every change is recorded in the stock ledger."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => null]">
        <x-slot:actions>
            @can('manage-inventory')
                <x-ui.button variant="secondary" icon="box-arrow-up" :href="route('inventory.stock-out.form')">Stock out</x-ui.button>
            @endcan
            @can('manage-inventory')
                <x-ui.button :href="route('inventory.stock-in.form')" icon="box-arrow-in-down">Stock in</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('inventory.partials.module-tabs', ['active' => 'levels'])

    <x-ui.filters :action="route('inventory.index')" search-placeholder="Name, generic name, barcode or batch"
        :labels="['status' => 'Showing', 'category' => 'Category']"
        :options="['status' => $statusOptions, 'category' => $categoryOptions]">
        <x-slot:inline>
            <x-ui.select name="status" size="sm" aria-label="Which medicines" :options="$statusOptions" :selected="$status" />
        </x-slot:inline>
        <x-ui.select name="category" label="Category" size="sm" :options="$categoryOptions" placeholder="All categories" :selected="$category" />
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table responsive="stack" :paginator="$medicines" noun="medicines" caption="Medicine stock">
            <x-slot:head>
                <x-ui.th>Medicine</x-ui.th>
                <x-ui.th priority="md" data-phone-sub>Category</x-ui.th>
                <x-ui.th align="end" data-phone-sub>On hand</x-ui.th>
                <x-ui.th data-phone-right>Stock</x-ui.th>
                <x-ui.th priority="lg">Expiry</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($medicines as $med)
                <tr>
                    <x-ui.td identity>
                        @can('view-medicines')
                            <a href="{{ route('medicines.show', $med) }}" class="cell-title d-inline-block">{{ $med->name }}</a>
                        @else
                            <span class="cell-title">{{ $med->name }}</span>
                        @endcan
                        @unless ($med->is_active)
                            <x-ui.status-badge :status="false" type="patient" size="sm" class="ms-1" />
                        @endunless
                        @if ($med->generic_name || $med->batch_number)
                            <span class="cell-sub d-block">{{ $med->generic_name ?: 'Batch '.$med->batch_number }}</span>
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
                    <x-ui.td label="Stock">@include('medicines.partials.stock-status', ['medicine' => $med])</x-ui.td>
                    <x-ui.td priority="lg" label="Expiry">@include('medicines.partials.expiry', ['date' => $med->expiration_date])</x-ui.td>
                    <x-ui.td actions>
                        @canany(['view-medicines', 'manage-inventory'])
                            <x-ui.action-menu :for="$med->name">
                                @can('view-medicines')
                                    <x-ui.action-menu.item :href="route('medicines.show', $med)" icon="eye">View medicine</x-ui.action-menu.item>
                                @endcan
                                @can('manage-inventory')
                                    <x-ui.action-menu.item :href="route('inventory.stock-in.form', ['medicine_id' => $med->id])" icon="box-arrow-in-down">Stock in</x-ui.action-menu.item>
                                    @if ($med->quantity > 0)
                                        <x-ui.action-menu.item :href="route('inventory.stock-out.form', ['medicine_id' => $med->id])" icon="box-arrow-up">Stock out</x-ui.action-menu.item>
                                    @endif
                                @endcan
                            </x-ui.action-menu>
                        @endcanany
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($hasFilters)
                    <x-ui.empty-state icon="search" title="No medicines match these filters" description="Try a different name or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('inventory.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="box-seam" title="No medicines in stock yet" description="Add medicines first, then record deliveries with stock in." compact />
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
