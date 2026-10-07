@extends('layouts.app')

@section('title', 'Medicines')

@php
    $stockOptions = ['low' => 'Low stock', 'out' => 'Out of stock', 'expiring' => 'Expiring soon', 'expired' => 'Expired'];
    $statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
    $categoryOptions = $categories->pluck('name', 'id')->all();
    $hasFilters = $filters['search'] !== '' || $filters['category'] || $filters['stockFilter'] || $filters['status'] !== 'all';
@endphp

@section('content')
<div class="vstack gap-4">

    <x-ui.page-header :title="'Medicines'"
        :description="number_format($medicines->total()).' '.\Illuminate\Support\Str::plural('medicine', $medicines->total()).' in the clinic list'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => null]">
        <x-slot:actions>
            @can('view-inventory')
                <x-ui.button variant="secondary" icon="box-seam" :href="route('inventory.index')">Inventory</x-ui.button>
            @endcan
            @can('create-medicines')
                <x-ui.button :href="route('medicines.create')" icon="plus-lg">Add medicine</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('medicines.partials.module-tabs', ['active' => 'all'])

    <div class="row g-3">
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Total medicines" :value="number_format($stats['total'])" icon="capsule" tone="brand"
            sub="In the clinic list" :href="route('medicines.index')" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Low stock" :value="number_format($stats['low'])" icon="exclamation-triangle" tone="warning"
            :sub="$stats['low'] ? 'Restock soon' : 'All above alert level'" :href="route('medicines.index', ['stock' => 'low'])" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Expiring soon" :value="number_format($stats['expiring'])" icon="hourglass-split" tone="warning"
            :sub="'Within '.\App\Models\Medicine::expiryWarningDays().' days'" :href="route('medicines.index', ['stock' => 'expiring'])" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Out of stock" :value="number_format($stats['out'])" icon="x-circle" :tone="$stats['out'] ? 'danger' : 'neutral'"
            :sub="$stats['out'] ? 'Cannot be given out' : 'None'" :href="route('medicines.index', ['stock' => 'out'])" /></div>
    </div>

    <x-ui.filters :action="route('medicines.index')" search-placeholder="Name, generic name, barcode, batch or supplier"
        :labels="['stock' => 'Stock', 'category' => 'Category', 'status' => 'Status']"
        :options="['stock' => $stockOptions, 'category' => $categoryOptions, 'status' => $statusOptions + ['all' => 'Any status']]">
        <x-slot:inline>
            <x-ui.select name="stock" size="sm" aria-label="Stock level" :options="$stockOptions" placeholder="All stock levels"
                :selected="$filters['stockFilter']" />
        </x-slot:inline>
        <x-ui.select name="category" label="Category" size="sm" :options="$categoryOptions" placeholder="All categories"
            :selected="$filters['category']" />
        <x-ui.select name="status" label="Status" size="sm" :options="$statusOptions" placeholder="Any status"
            :selected="$filters['status'] === 'all' ? null : $filters['status']" />
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table responsive="stack" :paginator="$medicines" noun="medicines" caption="Medicines">
            <x-slot:head>
                <x-ui.th>Medicine</x-ui.th>
                <x-ui.th priority="md" data-phone-sub>Category</x-ui.th>
                <x-ui.th align="end" data-phone-sub>On hand</x-ui.th>
                <x-ui.th data-phone-right>Stock</x-ui.th>
                <x-ui.th priority="lg">Expiry</x-ui.th>
                <x-ui.th priority="xl">Supplier</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($medicines as $med)
                @php $medSub = $med->generic_name ?: ($med->batch_number ? 'Lot '.$med->batch_number : null); @endphp
                <tr>
                    <x-ui.td identity>
                        <a href="{{ route('medicines.show', $med) }}" class="cell-title d-inline-block">{{ $med->name }}</a>
                        @unless ($med->is_active)
                            <x-ui.status-badge :status="false" type="patient" size="sm" class="ms-1" />
                        @endunless
                        @if ($medSub)
                            <span class="cell-sub d-block">{{ $medSub }}</span>
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
                    <x-ui.td priority="xl" label="Supplier" muted truncate>{{ $med->supplier ?: '-' }}</x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$med->name">
                            <x-ui.action-menu.item :href="route('medicines.show', $med)" icon="eye">View</x-ui.action-menu.item>
                            @can('update-medicines')
                                <x-ui.action-menu.item :href="route('medicines.edit', $med)" icon="pencil">Edit</x-ui.action-menu.item>
                            @endcan
                            @can('manage-inventory')
                                <x-ui.action-menu.item :href="route('inventory.stock-in.form', ['medicine_id' => $med->id])" icon="box-arrow-in-down">Stock in</x-ui.action-menu.item>
                                @if ($med->quantity > 0)
                                    <x-ui.action-menu.item :href="route('inventory.stock-out.form', ['medicine_id' => $med->id])" icon="box-arrow-up">Stock out</x-ui.action-menu.item>
                                @endif
                            @endcan
                            @can('delete-medicines')
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item :action="route('medicines.destroy', $med)" method="DELETE" icon="trash" danger
                                    confirm="It is removed from the medicine list. Its stock history is kept."
                                    :confirm-title="'Delete '.$med->name.'?'" confirm-button="Delete medicine">Delete</x-ui.action-menu.item>
                            @endcan
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($hasFilters)
                    <x-ui.empty-state icon="search" title="No medicines match these filters" description="Try a different name or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('medicines.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="capsule" title="No medicines yet" description="Add the medicines the clinic keeps in stock." compact>
                        @can('create-medicines')
                            <x-ui.button size="sm" icon="plus-lg" :href="route('medicines.create')">Add medicine</x-ui.button>
                        @endcan
                    </x-ui.empty-state>
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
