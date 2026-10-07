@extends('layouts.app')

@section('title', $medicine->name)

@php
    $usable = $medicine->availableQuantity();
    $expired = $medicine->expiredQuantity();
    $unitPlural = \Illuminate\Support\Str::plural($medicine->unit ?: 'unit');
@endphp

@section('content')
<div class="vstack gap-4">

    <x-ui.page-header :title="$medicine->name"
        :description="trim(($medicine->generic_name ? $medicine->generic_name.', ' : '').($medicine->category->name ?? 'No category'))"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => route('medicines.index'), $medicine->name => null]">
        @include('medicines.partials.stock-status', ['medicine' => $medicine])
        @unless ($medicine->is_active)
            <x-ui.status-badge :status="false" type="patient" size="sm" />
        @endunless
        <x-slot:actions>
            @canany(['update-medicines', 'manage-inventory', 'view-inventory'])
                <x-ui.dropdown label="More" variant="secondary">
                    @can('update-medicines')
                        <x-ui.dropdown-item :href="route('medicines.edit', $medicine)" icon="pencil">Edit medicine</x-ui.dropdown-item>
                    @endcan
                    @can('manage-inventory')
                        @if ($medicine->quantity > 0)
                            <x-ui.dropdown-item :href="route('inventory.stock-out.form', ['medicine_id' => $medicine->id])" icon="box-arrow-up">Stock out</x-ui.dropdown-item>
                        @endif
                    @endcan
                    @can('view-inventory')
                        <x-ui.dropdown-item :href="route('inventory.transactions', ['search' => $medicine->name])" icon="clock-history">All stock movements</x-ui.dropdown-item>
                    @endcan
                </x-ui.dropdown>
            @endcanany
            @can('manage-inventory')
                <x-ui.button :href="route('inventory.stock-in.form', ['medicine_id' => $medicine->id])" icon="box-arrow-in-down">Stock in</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $onHandTone = $medicine->quantity <= 0 ? 'danger' : ($medicine->is_low_stock ? 'warning' : 'success');
        $onHandSub = $medicine->quantity <= 0 ? 'Out of stock' : ($medicine->is_low_stock ? 'Low stock, restock soon' : $unitPlural.' in stock');
    @endphp
    <div class="row g-3">
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="On hand" :value="number_format($medicine->quantity)" icon="box-seam" :tone="$onHandTone" :sub="$onHandSub" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Usable" :value="number_format($usable)" icon="check2-circle" tone="success" sub="Not expired" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="In expired batches" :value="number_format($expired)" icon="calendar-x" :tone="$expired > 0 ? 'danger' : 'neutral'" :sub="$expired > 0 ? 'Needs disposal' : 'Nothing expired'" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat-card class="h-100" label="Low stock alert at" :value="number_format($medicine->low_stock_threshold)" icon="bell" tone="warning" :sub="$unitPlural.' or less'" /></div>
    </div>

    @if ($expired > 0)
        <x-ui.alert variant="danger" title="Expired stock on the shelf">
            {{ number_format($expired) }} {{ \Illuminate\Support\Str::plural($medicine->unit ?: 'unit', $expired) }} are in expired batches and cannot be given out.
            @can('dispose-medicines')
                Dispose of them from the batch list below or on the expiry page.
            @endcan
            @can('dispose-medicines')
                <x-slot:actions>
                    <x-ui.button size="sm" variant="secondary" :href="route('medicines.expiring', ['view' => 'expired'])">Open expiry page</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.alert>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <x-ui.card title="Details">
                <x-ui.description-list layout="stacked" empty="-">
                    <x-ui.description-item label="Generic name" empty="-">{{ $medicine->generic_name }}</x-ui.description-item>
                    <x-ui.description-item label="Barcode" empty="-">@if ($medicine->barcode)<span class="font-monospace">{{ $medicine->barcode }}</span>@endif</x-ui.description-item>
                    <x-ui.description-item label="Category" empty="-">{{ $medicine->category->name ?? '' }}</x-ui.description-item>
                    <x-ui.description-item label="Unit" empty="-">{{ ucfirst((string) $medicine->unit) }}</x-ui.description-item>
                    <x-ui.description-item label="Purchase price" empty="-">@if ($medicine->purchase_price !== null)<span class="tabular">{{ number_format((float) $medicine->purchase_price, 2) }}</span> per {{ $medicine->unit ?: 'unit' }}@endif</x-ui.description-item>
                    <x-ui.description-item label="Earliest expiry" empty="-">@if ($medicine->expiration_date)@include('medicines.partials.expiry', ['date' => $medicine->expiration_date])@endif</x-ui.description-item>
                    <x-ui.description-item label="Supplier" empty="-">{{ $medicine->supplier }}</x-ui.description-item>
                    <x-ui.description-item label="Status"><x-ui.status-badge :status="(bool) $medicine->is_active" type="patient" size="sm" /></x-ui.description-item>
                    <x-ui.description-item label="Description" empty="-">{{ $medicine->description }}</x-ui.description-item>
                    <x-ui.description-item label="Added" empty="-">{{ $medicine->created_at?->format('M j, Y') }}@if ($medicine->createdBy) by {{ $medicine->createdBy->name }}@endif</x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        </div>

        <div class="col-lg-8 vstack gap-4">
            <x-ui.card flush title="Batches" subtitle="Given out earliest expiry first" id="batches">
                <x-ui.table responsive="stack" dense caption="Batches">
                    <x-slot:head>
                        <x-ui.th>Batch no.</x-ui.th>
                        <x-ui.th>Expiry</x-ui.th>
                        <x-ui.th align="end">Left</x-ui.th>
                        <x-ui.th align="end" priority="md">Received</x-ui.th>
                        <x-ui.th align="end" priority="lg">Cost per unit</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                    </x-slot:head>

                    @foreach ($batches as $b)
                        @php
                            $inactiveBatch = $b->is_disposed || $b->quantity <= 0;
                            $batchLabel = $b->status === 'expiring'
                                ? (($d = $b->days_until_expiry) === 0 ? 'Expires today' : ($d === 1 ? 'Expires tomorrow' : 'Expires in '.$d.' days'))
                                : $b->status_label;
                            $canEditBatch = ! $b->is_disposed && auth()->user()?->can('update-medicines');
                            $canDispose = ! $b->is_disposed && $b->quantity > 0 && auth()->user()?->can('dispose-medicines');
                        @endphp
                        <tr @class(['text-muted' => $inactiveBatch])>
                            <x-ui.td identity>
                                <span class="cell-title d-block font-monospace">{{ $b->batch_number ?: 'No batch no.' }}</span>
                                @if ($b->received_at)<span class="cell-sub d-block">Received {{ $b->received_at->format('M j, Y') }}</span>@endif
                            </x-ui.td>
                            <x-ui.td label="Expiry">@include('medicines.partials.expiry', ['date' => $b->expiry_date, 'badge' => false, 'empty' => 'No expiry'])</x-ui.td>
                            <x-ui.td numeric label="Left">@include('medicines.partials.qty', ['qty' => $b->quantity, 'unit' => $medicine->unit])</x-ui.td>
                            <x-ui.td numeric priority="md" label="Received"><span class="tabular">{{ number_format($b->initial_quantity) }}</span></x-ui.td>
                            <x-ui.td numeric priority="lg" label="Cost per unit">{{ $b->unit_cost !== null ? number_format((float) $b->unit_cost, 2) : '-' }}</x-ui.td>
                            <x-ui.td label="Status"><x-ui.badge :color="$b->status === 'expiring' ? 'orange' : $b->status_color" :icon="['expired' => 'calendar-x', 'expiring' => 'hourglass-split'][$b->status] ?? null" size="sm">{{ $batchLabel }}</x-ui.badge></x-ui.td>
                            <x-ui.td actions>
                                @if ($canEditBatch || $canDispose)
                                    <x-ui.action-menu :for="'batch '.($b->batch_number ?: $b->id)">
                                        @if ($canEditBatch)
                                            <x-ui.action-menu.item icon="pencil" class="btn-edit-batch"
                                                data-action="{{ route('medicines.batches.update', [$medicine, $b]) }}"
                                                data-batch-number="{{ $b->batch_number }}"
                                                data-expiry="{{ $b->expiry_date?->toDateString() }}"
                                                data-cost="{{ $b->unit_cost }}"
                                                data-supplier="{{ $b->supplier }}">Correct details</x-ui.action-menu.item>
                                        @endif
                                        @if ($canDispose)
                                            @if ($canEditBatch)<x-ui.action-menu.divider />@endif
                                            <x-ui.action-menu.item icon="trash3" danger class="btn-dispose"
                                                data-action="{{ route('disposals.store', $b) }}"
                                                data-label="{{ $medicine->name }}, {{ $b->label }}"
                                                data-qty="{{ number_format($b->quantity) }} {{ \Illuminate\Support\Str::plural($medicine->unit ?: 'unit', $b->quantity) }}"
                                                data-expired="{{ $b->is_expired ? '1' : '0' }}">Dispose...</x-ui.action-menu.item>
                                        @endif
                                    </x-ui.action-menu>
                                @endif
                            </x-ui.td>
                        </tr>
                    @endforeach

                    <x-slot:empty>
                        <x-ui.empty-state icon="stack" title="No batches yet" description="Stock in to add the first batch." compact>
                            @can('manage-inventory')
                                <x-ui.button size="sm" icon="box-arrow-in-down" :href="route('inventory.stock-in.form', ['medicine_id' => $medicine->id])">Stock in</x-ui.button>
                            @endcan
                        </x-ui.empty-state>
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>

            <x-ui.card flush title="Recent stock movements">
                @can('view-inventory')
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary" :href="route('inventory.transactions', ['search' => $medicine->name])">All movements</x-ui.button>
                    </x-slot:actions>
                @endcan
                <x-ui.table responsive="stack" dense caption="Recent stock movements">
                    <x-slot:head>
                        <x-ui.th data-phone-sub>Date</x-ui.th>
                        <x-ui.th data-phone-title>Movement</x-ui.th>
                        <x-ui.th align="end" data-phone-right>Change</x-ui.th>
                        <x-ui.th align="end" priority="md">Stock</x-ui.th>
                        <x-ui.th priority="lg">Notes</x-ui.th>
                    </x-slot:head>

                    @foreach ($transactions as $txn)
                        <tr>
                            <x-ui.td identity>
                                <span class="d-block">{{ $txn->created_at->format('M j, Y') }}</span>
                                <span class="cell-sub d-block">{{ $txn->created_at->format('g:i A') }}</span>
                            </x-ui.td>
                            <x-ui.td label="Movement">
                                <x-ui.badge :color="$txn->type_badge" size="sm">{{ $txn->type_label }}</x-ui.badge>
                                @if ($txn->batch_number)<span class="text-muted fs-xs font-monospace ms-1">{{ $txn->batch_number }}</span>@endif
                            </x-ui.td>
                            <x-ui.td numeric label="Change"><span class="fw-semibold">{{ $txn->quantity > 0 ? '+' : '' }}{{ number_format($txn->quantity) }}</span></x-ui.td>
                            <x-ui.td numeric priority="md" label="Stock"><span class="text-muted">{{ number_format($txn->before_quantity) }} to</span> {{ number_format($txn->after_quantity) }}</x-ui.td>
                            <x-ui.td priority="lg" label="Notes" muted truncate>{{ $txn->notes ?: '-' }}</x-ui.td>
                        </tr>
                    @endforeach

                    <x-slot:empty>
                        <x-ui.empty-state quiet icon="arrow-left-right" title="No stock movements recorded yet." />
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
</div>

@can('update-medicines')
    <x-ui.modal id="editBatchModal" title="Correct batch details" subtitle="Quantities cannot be changed here. Use stock in, stock out or dispose so the ledger stays correct."
        action="#" method="PATCH" sheet>
        <div class="row g-3">
            <x-ui.input wrapper-class="col-12 col-sm-6" name="batch_number" id="ebNumber" label="Batch number" maxlength="100" />
            <x-ui.input wrapper-class="col-12 col-sm-6" name="expiry_date" id="ebExpiry" type="date" label="Expiry date" />
            <x-ui.input wrapper-class="col-12 col-sm-6" name="unit_cost" id="ebCost" type="number" label="Cost per unit" min="0" step="0.01" />
            <x-ui.input wrapper-class="col-12 col-sm-6" name="supplier" id="ebSupplier" label="Supplier" maxlength="200" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check-lg">Save batch</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endcan

@include('medicines.partials.dispose-modal')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('editBatchModal');
    if (!modalEl) return;
    var form = modalEl.querySelector('form');
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-edit-batch');
        if (!btn) return;
        e.preventDefault();
        form.action = btn.dataset.action;
        document.getElementById('ebNumber').value   = btn.dataset.batchNumber || '';
        document.getElementById('ebExpiry').value   = btn.dataset.expiry || '';
        document.getElementById('ebCost').value     = btn.dataset.cost || '';
        document.getElementById('ebSupplier').value = btn.dataset.supplier || '';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
});
</script>
@endpush
