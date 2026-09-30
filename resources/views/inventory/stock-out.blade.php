@extends('layouts.app')

@section('title', 'Stock out')

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Stock out'"
        description="Remove stock that was damaged, lost or counted wrong. Expired batches are disposed of from the expiry page."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('inventory.index'), 'Stock out' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <div class="row">
        <div class="col-xl-10">
            <form method="POST" action="{{ route('inventory.stock-out') }}">
                @csrf
                <x-ui.card>
                    <x-ui.section title="Medicine" description="Only medicines with stock on hand are listed.">
                        <div class="vstack gap-3">
                            <x-ui.field label="Medicine" for="medicineSelect" required>
                                <x-ui.select name="medicine_id" id="medicineSelect" placeholder="Select a medicine" :selected="$selected" required>
                                    @foreach ($medicines as $med)
                                        @php
                                            $usableQty = $med->batches->filter(fn ($b) => ! $b->is_expired)->sum('quantity');
                                            $batchData = $med->batches->map(fn ($b) => [
                                                'id' => $b->id, 'label' => $b->label, 'qty' => $b->quantity, 'expired' => $b->is_expired,
                                            ])->values();
                                        @endphp
                                        <option value="{{ $med->id }}"
                                            data-unit="{{ $med->unit }}"
                                            data-qty="{{ $usableQty }}"
                                            data-batches="{{ json_encode($batchData) }}"
                                            @selected(old('medicine_id', $selected) == $med->id)>
                                            {{ $med->name }}: {{ number_format($usableQty) }} usable{{ $med->is_active ? '' : ' (inactive)' }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field label="Batch" name="batch_id" for="batchSelect" optional help="Pick a batch if a specific lot was damaged or lost.">
                                <select name="batch_id" id="batchSelect" @class(['form-select', 'is-invalid' => $errors->has('batch_id')]) data-old="{{ old('batch_id') }}"
                                    aria-describedby="{{ $errors->has('batch_id') ? 'batchSelect-error' : 'batchSelect-help' }}">
                                    <option value="">Earliest expiry first (automatic)</option>
                                </select>
                            </x-ui.field>
                        </div>
                    </x-ui.section>

                    <x-ui.section title="What to remove">
                        <div class="row g-3">
                            <x-ui.field class="col-12 col-sm-6" label="Quantity to remove" name="quantity" for="qtyInput" required>
                                <input type="number" name="quantity" id="qtyInput" value="{{ old('quantity') }}" min="1" required inputmode="numeric"
                                    @class(['form-control', 'is-invalid' => $errors->has('quantity')]) aria-describedby="stockInfo{{ $errors->has('quantity') ? ' qtyInput-error' : '' }}">
                                <div class="form-text d-none" id="stockInfo">Available: <strong id="currentStock" class="tabular">0</strong> <span id="unitLabel">units</span></div>
                            </x-ui.field>
                            <x-ui.textarea wrapper-class="col-12" name="notes" id="notes" label="Reason" rows="3" required
                                placeholder="For example: damaged packaging, count correction" />
                        </div>
                    </x-ui.section>

                    <x-slot:footer>
                        <x-ui.button variant="secondary" :href="route('inventory.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="dash-lg">Remove stock</x-ui.button>
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
    const medSel    = document.getElementById('medicineSelect');
    const batchSel  = document.getElementById('batchSelect');
    const stockInfo = document.getElementById('stockInfo');
    const qtyInput  = document.getElementById('qtyInput');
    if (!medSel || !batchSel) return;
    let batches = [];

    function setMax(qty, unit) {
        document.getElementById('currentStock').textContent = qty;
        document.getElementById('unitLabel').textContent = unit + '(s)';
        qtyInput.max = qty;
        stockInfo.classList.remove('d-none');
    }

    function onMedicine() {
        const opt = medSel.selectedOptions[0];
        batchSel.querySelectorAll('option:not(:first-child)').forEach(o => o.remove());
        if (!medSel.value) { stockInfo.classList.add('d-none'); qtyInput.removeAttribute('max'); return; }
        batches = JSON.parse(opt.dataset.batches || '[]');
        batches.forEach(b => {
            const o = document.createElement('option');
            o.value = b.id;
            o.textContent = b.label + ': ' + b.qty + (b.expired ? ' (expired, use Dispose)' : '');
            o.disabled = b.expired;
            batchSel.appendChild(o);
        });
        if (batchSel.dataset.old) { batchSel.value = batchSel.dataset.old; batchSel.dataset.old = ''; }
        onBatch();
    }

    function onBatch() {
        const opt = medSel.selectedOptions[0];
        const b = batches.find(x => String(x.id) === batchSel.value);
        setMax(b ? b.qty : parseInt(opt.dataset.qty, 10), opt.dataset.unit);
    }

    medSel.addEventListener('change', onMedicine);
    batchSel.addEventListener('change', onBatch);
    if (medSel.value) onMedicine();
});
</script>
@endpush
