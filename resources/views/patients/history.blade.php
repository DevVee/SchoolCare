@extends('layouts.app')
@use('App\Support\DisplayFormat')

@section('title', 'Visit history: ' . $patient->full_name)

@php
    $types = $timeline->pluck('type')->unique()->values();
    $clinicName = settings('clinic_name') ?: settings('app_name');
    $orgName = trim((string) settings('org_name', ''));
    $typeModules = ['Clinic visit' => 'logbook', 'Consultation' => 'consultations', 'Medicine given' => 'dispensing', 'Appointment' => 'appointments'];
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Visit history" class="no-print"
        :description="'Every clinic visit, consultation, medicine and appointment of '.$patient->full_name.', newest first.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), $patient->full_name => route('patients.show', $patient->id), 'History' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="clipboard2-data" :href="route('patients.health-report', $patient->id)">Health report</x-ui.button>
            <x-ui.button icon="printer" onclick="window.print()">Print</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Print heading --}}
    <div class="print-only">
        <div class="small">{{ $clinicName }}@if ($orgName !== ''), {{ $orgName }}@endif</div>
        <h1 class="h5 mb-0">Visit history: {{ $patient->full_name }} ({{ $patient->patient_number }})</h1>
        <div class="small">Printed {{ DisplayFormat::date(now()) }}</div>
    </div>

    @if ($types->count() > 1)
        <div class="nav nav-segmented no-print align-self-start" role="group" aria-label="Show record types">
            <button type="button" class="nav-link active" data-filter="" aria-pressed="true">All <span class="nav-count">{{ $timeline->count() }}</span></button>
            @foreach ($types as $type)
                <button type="button" class="nav-link" data-filter="{{ $type }}" aria-pressed="false">{{ $type }} <span class="nav-count">{{ $timeline->where('type', $type)->count() }}</span></button>
            @endforeach
        </div>
    @endif

    <x-ui.card flush>
        <x-ui.table responsive="stack" caption="Visit history" id="historyTable">
            <x-slot:head>
                <x-ui.th>Date</x-ui.th>
                <x-ui.th>Type</x-ui.th>
                <x-ui.th>Details</x-ui.th>
                <x-ui.th priority="md">By</x-ui.th>
                <x-ui.th align="end" class="no-print"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>
            @foreach ($timeline as $item)
                <tr data-type="{{ $item['type'] }}">
                    <x-ui.td identity>
                        <span class="fw-semibold">{{ DisplayFormat::date($item['date']) }}</span>
                        <span class="text-muted small ms-1">{{ DisplayFormat::time($item['time']) }}</span>
                    </x-ui.td>
                    <x-ui.td label="Type">
                        <span class="d-inline-flex align-items-center gap-2">
                            <x-ui.icon :name="$item['icon']" class="tone-{{ $typeModules[$item['type']] ?? 'brand' }}" />{{ $item['type'] }}
                        </span>
                    </x-ui.td>
                    <x-ui.td label="Details" wrap>
                        <div>{{ $item['title'] }}</div>
                        @if ($item['detail'])<div class="small text-muted">{{ $item['detail'] }}</div>@endif
                    </x-ui.td>
                    <x-ui.td priority="md" label="By" muted>{{ $item['by'] ?: '-' }}</x-ui.td>
                    <x-ui.td actions class="no-print">
                        <x-ui.action-menu :for="strtolower($item['type']).' on '.DisplayFormat::date($item['date'])">
                            <x-ui.action-menu.item :href="$item['url']" icon="eye">Open {{ strtolower($item['type']) }}</x-ui.action-menu.item>
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach
            <x-slot:empty>
                <x-ui.empty-state module="logbook" title="No records yet for this patient" compact />
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection

@push('styles')
<style>
.print-only { display: none; }
@media print {
    .no-print, footer { display: none !important; }
    .print-only { display: block; }
    #historyTable tr { break-inside: avoid; }
    @page { margin: 14mm; }
}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
        const type = btn.dataset.filter;
        document.querySelectorAll('#historyTable tbody tr[data-type]').forEach(tr => { tr.hidden = type !== '' && tr.dataset.type !== type; });
        document.querySelectorAll('[data-filter]').forEach(b => {
            b.classList.toggle('active', b === btn);
            b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
        });
    });
});
</script>
@endpush
