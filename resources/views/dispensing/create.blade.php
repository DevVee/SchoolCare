@extends('layouts.app')

@section('title', 'Dispense Medicine')

@section('content')
<x-ui.page-header title="Dispense medicine" description="Record a medicine given to a patient. Stock is deducted, earliest expiry first."
    :back="route('dispensing.index')" back-label="Back to dispensing records" />

@if($errors->any())
    <x-ui.alert variant="danger" title="Please fix the errors below" class="mb-3">Nothing was saved and no stock was deducted.</x-ui.alert>
@endif

<form method="POST" action="{{ route('dispensing.store') }}">
    @csrf
    <x-ui.card padding="lg">
        <x-ui.section title="Patient" description="Link a consultation from the last 30 days, or leave it as walk-in.">
            <div class="row g-3">
                <x-ui.select wrapper-class="col-12" name="patient_id" id="patientSelect" label="Patient" required
                    placeholder="Select a patient" :selected="$selectedPatient"
                    :options="$patients->mapWithKeys(fn ($p) => [$p->id => $p->last_name.', '.$p->first_name.($p->middle_name ? ' '.mb_substr($p->middle_name, 0, 1).'.' : '').' ('.$p->patient_number.')'])->all()" />
                <x-ui.select wrapper-class="col-12" name="consultation_id" id="consultSelect" label="Consultation" optional
                    placeholder="None (walk-in dispensing)" :options="[]" />
            </div>
        </x-ui.section>

        <x-ui.section title="Medicine" description="Only active, unexpired medicines with stock on hand are listed.">
            @php $medError = $errors->first('medicine_id'); @endphp
            <x-ui.field label="Medicine" name="medicine_id" for="medicineSelect" required>
                <select name="medicine_id" id="medicineSelect" required
                        @class(['form-select', 'is-invalid' => $medError])
                        @if($medError) aria-invalid="true" aria-describedby="medicineSelect-error" @endif>
                    <option value="">Select a medicine</option>
                    @foreach($medicines as $med)
                    @php
                        $usable = (int) ($med->usable_quantity ?? $med->quantity);
                        $expiry = $med->next_expiry ? \App\Support\DisplayFormat::date($med->next_expiry) : '';
                    @endphp
                    <option value="{{ $med->id }}"
                        data-unit="{{ $med->unit }}"
                        data-qty="{{ $usable }}"
                        data-low="{{ $med->low_stock_threshold }}"
                        data-expiry="{{ $expiry }}"
                        data-expiring="{{ $med->is_expiring_soon ? 1 : 0 }}"
                        @selected(old('medicine_id') == $med->id)>
                        {{ $med->name }}: {{ $usable }} {{ $med->unit }}(s) usable{{ $expiry ? ', next expiry '.$expiry : '' }}{{ $med->is_expiring_soon ? ' (expiring soon)' : '' }}
                    </option>
                    @endforeach
                </select>
            </x-ui.field>

            {{-- Stock indicator --}}
            <div id="stockBar" class="mt-3 d-none" aria-live="polite">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-ink-2">Available stock</span>
                    <span id="stockQty" class="fw-semibold tabular"></span>
                </div>
                <div id="expiryNote" class="small mb-1"></div>
                <div class="progress" style="height:6px;">
                    <div id="stockProgress" class="progress-bar" role="progressbar" style="width:0%" aria-label="Stock level"></div>
                </div>
            </div>
        </x-ui.section>

        <x-ui.section title="Quantity and remarks">
            <div class="row g-3">
                <x-ui.input wrapper-class="col-12 col-sm-4" type="number" name="quantity" id="qtyInput" label="Quantity" required
                    min="1" :value="1" />
                <x-ui.textarea wrapper-class="col-12" name="remarks" label="Remarks" optional rows="2"
                    placeholder="Dosage instructions or special notes" />
            </div>
        </x-ui.section>

        <div class="save-bar">
            <x-ui.button variant="secondary" :href="route('dispensing.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check-lg">Dispense</x-ui.button>
        </div>
    </x-ui.card>
</form>

@push('scripts')
<script>
const consultations = @json($consultations->groupBy('patient_id'));
const patientSel  = document.getElementById('patientSelect');
const consultSel  = document.getElementById('consultSelect');
const medSel      = document.getElementById('medicineSelect');
const stockBar    = document.getElementById('stockBar');
const stockQty    = document.getElementById('stockQty');
const stockProg   = document.getElementById('stockProgress');
const qtyInput    = document.getElementById('qtyInput');
const expiryNote  = document.getElementById('expiryNote');
const oldConsult  = @json((string) old('consultation_id'));

function populateConsultations(patientId) {
    consultSel.innerHTML = '<option value="">None (walk-in dispensing)</option>';
    (consultations[patientId] || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        // visit_date serialises as an ISO timestamp: use its date part only.
        const ymd = String(c.visit_date).substring(0, 10).split('-').map(Number);
        const d = new Date(ymd[0], ymd[1] - 1, ymd[2]).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
        opt.textContent = d + ', ' + (c.chief_complaint ? c.chief_complaint.substring(0, 50) : 'Visit');
        if (oldConsult == String(c.id)) opt.selected = true;
        consultSel.appendChild(opt);
    });
}

patientSel.addEventListener('change', function () {
    populateConsultations(this.value);
});

medSel.addEventListener('change', function () {
    const opt = this.selectedOptions[0];
    if (this.value) {
        const qty  = parseInt(opt.dataset.qty);
        const low  = parseInt(opt.dataset.low);
        const pct  = Math.min(100, Math.round((qty / Math.max(low * 3, qty, 1)) * 100));
        stockQty.textContent = qty + ' ' + opt.dataset.unit + '(s)';
        if (opt.dataset.expiry) {
            expiryNote.textContent = 'Expires ' + opt.dataset.expiry + (opt.dataset.expiring === '1' ? ' (expiring soon)' : '');
            expiryNote.className = 'small mb-1 ' + (opt.dataset.expiring === '1' ? 'text-warning-emphasis fw-semibold' : 'text-muted');
        } else {
            expiryNote.textContent = 'No expiry date recorded';
            expiryNote.className = 'small mb-1 text-muted';
        }
        stockProg.style.width = pct + '%';
        stockProg.className   = 'progress-bar bg-' + (qty === 0 ? 'danger' : qty <= low ? 'warning' : 'success');
        qtyInput.max = qty;
        stockBar.classList.remove('d-none');
    } else {
        stockBar.classList.add('d-none');
        qtyInput.removeAttribute('max');
    }
});

// Initialise on load
if (patientSel.value) populateConsultations(patientSel.value);
if (medSel.value)     medSel.dispatchEvent(new Event('change'));
</script>
@endpush
@endsection
