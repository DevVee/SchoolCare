@extends('layouts.app')

@section('title', 'Edit medicine')

@php
    $unitOptions = collect(array_unique(array_merge(settings()->list('medicine_units'), array_filter([$medicine->unit]))))
        ->mapWithKeys(fn ($u) => [$u => ucfirst($u)])->all();
    $categoryOptions = $categories->pluck('name', 'id')->all();
@endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Edit medicine'" :description="$medicine->name"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => route('medicines.index'), $medicine->name => route('medicines.show', $medicine), 'Edit' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('medicines.update', $medicine) }}">
        @csrf @method('PUT')
        <x-ui.card>
            <x-ui.section title="Medicine" description="How the medicine appears in lists, searches and reports.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12" name="name" label="Medicine name" required :value="$medicine->name" />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="generic_name" label="Generic name" maxlength="200" placeholder="For example: Paracetamol"
                        :value="$medicine->generic_name" />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="barcode" label="Barcode" maxlength="100" autocomplete="off" class="font-monospace"
                        placeholder="Scan or type" help="Optional. Used to find this medicine with a scanner." :value="$medicine->barcode" />
                    <x-ui.select wrapper-class="col-12 col-sm-6" name="category_id" label="Category" :options="$categoryOptions" placeholder="Select category" required
                        :selected="$medicine->category_id" />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="supplier" label="Supplier or manufacturer" :value="$medicine->supplier" />
                    <x-ui.textarea wrapper-class="col-12" name="description" label="Description" rows="3" :value="$medicine->description" />
                </div>
            </x-ui.section>

            <x-ui.section title="Stock" description="Quantities change only through stock in, stock out and dispensing, so every change is in the ledger.">
                <div class="row g-3">
                    {{-- Read-only: stock changes only through the inventory ledger --}}
                    <x-ui.field class="col-12 col-sm-6" label="Quantity on hand" for="f-quantity-readonly">
                        <input type="number" id="f-quantity-readonly" class="form-control" value="{{ $medicine->quantity }}" readonly disabled aria-describedby="f-quantity-readonly-help">
                        <div class="form-text" id="f-quantity-readonly-help">
                            @can('manage-inventory')
                                Change it with <a href="{{ route('inventory.stock-in.form', ['medicine_id' => $medicine->id]) }}">stock in</a>
                                or <a href="{{ route('inventory.stock-out.form', ['medicine_id' => $medicine->id]) }}">stock out</a>.
                            @else
                                Changed with stock in or stock out.
                            @endcan
                        </div>
                    </x-ui.field>
                    <x-ui.select wrapper-class="col-12 col-sm-6" name="unit" label="Unit" :options="$unitOptions" required :selected="$medicine->unit" />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="low_stock_threshold" type="number" label="Low stock alert at" min="0" required
                        :value="$medicine->low_stock_threshold" help="You are alerted when stock drops to this number or below." />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="purchase_price" type="number" label="Purchase price per unit" min="0" step="0.01"
                        :value="$medicine->purchase_price" help="Optional. Pre-fills the cost when stocking in and values disposals." />
                </div>
                <x-ui.alert variant="neutral" class="mt-3">
                    Expiry dates and batch numbers are kept per batch. Correct them from the
                    <a href="{{ route('medicines.show', $medicine) }}#batches">batch list</a>.
                </x-ui.alert>
            </x-ui.section>

            <x-ui.section title="Availability">
                <x-ui.switch name="is_active" label="Active" description="Inactive medicines stay in the list but cannot be stocked in or given out."
                    :checked="(bool) $medicine->is_active" />
            </x-ui.section>

            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('medicines.show', $medicine)">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
