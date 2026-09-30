@extends('layouts.app')

@section('title', 'Stock in')

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Stock in'" description="Record a new batch of a medicine received from a supplier."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('inventory.index'), 'Stock in' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <div class="row">
        <div class="col-xl-10 vstack gap-4">
            {{-- Barcode scan: keyboard-wedge scanners type the code and press Enter. Kept outside the form so Enter only looks up. --}}
            <x-ui.card>
                <x-ui.field label="Scan barcode" for="barcodeInput">
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1 min-w-0">
                        <x-ui.input name="barcode_lookup" id="barcodeInput" icon="upc-scan" class="font-monospace" autocomplete="off" autofocus
                            placeholder="Scan or type a barcode, then press Enter" enterkeyhint="search"
                            data-lookup-url="{{ route('medicines.lookup') }}" aria-describedby="barcodeStatus" />
                        </div>
                        <x-ui.button variant="secondary" id="barcodeFind">Find</x-ui.button>
                    </div>
                    <div class="form-text" id="barcodeStatus" aria-live="polite">Or pick the medicine from the list below.</div>
                </x-ui.field>
            </x-ui.card>

            <form method="POST" action="{{ route('inventory.stock-in') }}" id="stockInForm">
                @csrf
                <x-ui.card>
                    <x-ui.section title="Medicine">
                        <x-ui.field label="Medicine" for="medicineSelect" required>
                            <x-ui.select name="medicine_id" id="medicineSelect" placeholder="Select a medicine" :selected="$selected" required>
                                @foreach ($medicines as $med)
                                    <option value="{{ $med->id }}"
                                        data-unit="{{ $med->unit }}"
                                        data-qty="{{ $med->quantity }}"
                                        data-price="{{ $med->purchase_price }}"
                                        @selected(old('medicine_id', $selected) == $med->id)>
                                        {{ $med->name }}{{ $med->generic_name ? ' ('.$med->generic_name.')' : '' }}: {{ number_format($med->quantity) }} in stock
                                    </option>
                                @endforeach
                            </x-ui.select>
                            <div class="form-text d-none" id="stockInfo">Current stock: <strong id="currentStock" class="tabular">0</strong> <span id="unitLabel">units</span></div>
                        </x-ui.field>
                    </x-ui.section>

                    <x-ui.section title="Batch received" description="Each delivery is kept as its own batch so the earliest expiry is given out first.">
                        <div class="row g-3">
                            <x-ui.input wrapper-class="col-12 col-sm-6" name="quantity" id="qtyInput" type="number" label="Quantity received" min="1" required inputmode="numeric" />
                            <x-ui.input wrapper-class="col-12 col-sm-6" name="batch_number" id="batchNumber" label="Batch or lot number" maxlength="100" />
                            <x-ui.input wrapper-class="col-12 col-sm-6" name="expiration_date" id="expiryInput" type="date" label="Expiry date"
                                min="{{ today()->addDay()->toDateString() }}" help="Leave empty only for supplies that do not expire." />
                            <x-ui.input wrapper-class="col-12 col-sm-6" name="unit_cost" id="unitCost" type="number" label="Cost per unit" min="0" step="0.01" />
                            <x-ui.input wrapper-class="col-12" name="supplier" id="supplier" label="Supplier" maxlength="200" />
                            <x-ui.textarea wrapper-class="col-12" name="notes" id="notes" label="Notes" rows="2" optional />
                        </div>
                    </x-ui.section>

                    <x-slot:footer>
                        <x-ui.button variant="secondary" :href="route('inventory.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="plus-lg">Add stock</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const medSel   = document.getElementById('medicineSelect');
    const unitCost = document.getElementById('unitCost');
    const barcode  = document.getElementById('barcodeInput');
    const status   = document.getElementById('barcodeStatus');
    if (!medSel || !barcode) return;

    function onMedicineChange() {
        const opt  = medSel.selectedOptions[0];
        const info = document.getElementById('stockInfo');
        if (medSel.value) {
            document.getElementById('currentStock').textContent = opt.dataset.qty;
            document.getElementById('unitLabel').textContent    = opt.dataset.unit + '(s)';
            info.classList.remove('d-none');
            if (!unitCost.value && opt.dataset.price) unitCost.value = opt.dataset.price;
        } else {
            info.classList.add('d-none');
        }
    }
    medSel.addEventListener('change', onMedicineChange);
    if (medSel.value) onMedicineChange();

    async function lookup() {
        const code = barcode.value.trim();
        if (!code) return;
        status.textContent = 'Looking up ' + code + '...';
        status.className = 'form-text';
        try {
            const url = barcode.dataset.lookupUrl + '?barcode=' + encodeURIComponent(code);
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (res.ok && data.found) {
                const opt = medSel.querySelector('option[value="' + data.medicine.id + '"]');
                if (!opt) {
                    status.textContent = data.medicine.name + ' is inactive and cannot be stocked in here.';
                    status.className = 'form-text text-danger';
                    return;
                }
                medSel.value = data.medicine.id;
                unitCost.value = '';
                onMedicineChange();
                status.textContent = 'Found: ' + data.medicine.name + '. Enter the quantity received.';
                status.className = 'form-text text-success';
                barcode.value = '';
                document.getElementById('qtyInput').focus();
            } else {
                status.textContent = data.message || 'No medicine found for that barcode.';
                status.className = 'form-text text-danger';
                barcode.select();
            }
        } catch (e) {
            status.textContent = 'Could not look up the barcode. Check your connection and try again.';
            status.className = 'form-text text-danger';
        }
    }
    barcode.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); lookup(); }
    });
    document.getElementById('barcodeFind').addEventListener('click', lookup);
});
</script>
@endpush
