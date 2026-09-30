@extends('layouts.app')

@section('title', 'Add medicine')

@php
    $unitOptions = collect(settings()->list('medicine_units'))->mapWithKeys(fn ($u) => [$u => ucfirst($u)])->all();
    $categoryOptions = $categories->pluck('name', 'id')->all();
@endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Add medicine'" description="Add a medicine to the clinic list with its opening stock."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => route('medicines.index'), 'Add medicine' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('medicines.store') }}">
        @csrf
        <x-ui.card>
            <x-ui.section title="Medicine" description="How the medicine appears in lists, searches and reports.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12" name="name" label="Medicine name" required placeholder="For example: Paracetamol 500mg" />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="generic_name" label="Generic name" maxlength="200" placeholder="For example: Paracetamol" />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="barcode" label="Barcode" maxlength="100" autocomplete="off" class="font-monospace"
                        placeholder="Scan or type" help="Optional. Used to find this medicine with a scanner." />
                    <x-ui.select wrapper-class="col-12 col-sm-6" name="category_id" label="Category" :options="$categoryOptions" placeholder="Select category" required />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="supplier" label="Supplier or manufacturer" placeholder="For example: PharmaCorp Inc." />
                    <x-ui.textarea wrapper-class="col-12" name="description" label="Description" rows="3" placeholder="Strength, form, notes" />
                </div>
            </x-ui.section>

            <x-ui.section title="Stock" description="The starting quantity is recorded in the stock ledger as the opening batch.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="quantity" type="number" label="Quantity" :value="0" min="0" required />
                    <x-ui.select wrapper-class="col-12 col-sm-6" name="unit" label="Unit" :options="$unitOptions" placeholder="Select unit" required />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="low_stock_threshold" type="number" label="Low stock alert at" min="0" required
                        :value="settings('low_stock_threshold', 10)" help="You are alerted when stock drops to this number or below." />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="purchase_price" type="number" label="Purchase price per unit" min="0" step="0.01"
                        help="Optional. Pre-fills the cost when stocking in and values disposals." />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="expiration_date" type="date" label="Expiry date (opening batch)" />
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="batch_number" label="Batch or lot number (opening batch)" placeholder="For example: LOT-2025-001" />
                </div>
            </x-ui.section>

            <x-ui.section title="Availability">
                <x-ui.switch name="is_active" label="Active" description="Active medicines can be stocked in and given out." :checked="true" />
            </x-ui.section>

            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('medicines.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Add medicine</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
