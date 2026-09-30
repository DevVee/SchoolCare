{{--
    "Currently in clinic": today's visits with a time in and no time out,
    each with an Out (discharge) button. Used on the logbook and dashboard.
    Optional: $inClinic (collection), $compact (bool, dashboard widget: hides the complaint).
    When nobody is in the clinic the panel collapses to one quiet line.
--}}
@can('view-patient-logs')
@php
    $inClinic ??= \App\Models\PatientLog::inClinic()->with('patient')->orderBy('time_in')->get();
    $compact  ??= false;
    $canDischarge  = auth()->user()->can('update-patient-logs');
    $dischargeSms  = settings('sms_enabled') && settings('notify_sms_clinic_discharge', true);
@endphp
@if($inClinic->isEmpty())
    <div class="card in-clinic-quiet mb-4" id="inClinicPanel">
        <div class="card-body py-0 px-3">
            <x-ui.empty-state quiet module="logbook" icon="door-open" title="Currently in clinic: no patients right now." />
        </div>
    </div>
@else
    <x-ui.card flush class="mb-4" id="inClinicPanel">
        <x-slot:header>
            <div class="d-flex align-items-center gap-2 min-w-0">
                <x-ui.icon-chip module="logbook" icon="door-open" />
                <h2 class="card-heading">Currently in clinic</h2>
                <x-ui.count :value="$inClinic->count()" :label="$inClinic->count().' '.Str::plural('patient', $inClinic->count()).' in the clinic'" />
            </div>
        </x-slot:header>
        <ul class="dash-list">
            @foreach($inClinic as $visit)
            @php $mins = (int) \Carbon\Carbon::parse($visit->log_date->toDateString().' '.$visit->time_in)->diffInMinutes(now(), false); @endphp
            <li class="dash-row">
                <div class="dash-row-main">
                    <div class="dash-row-title">
                        <a href="{{ route('patient-logs.show', $visit) }}">{{ $visit->patient?->full_name ?? 'Unknown patient' }}</a>
                        <x-archived-badge :patient="$visit->patient" />
                    </div>
                    <div class="dash-row-sub">
                        In at {{ \App\Support\DisplayFormat::time($visit->time_in) }}
                        @if($mins > 0)({{ $mins >= 60 ? intdiv($mins, 60).' h '.($mins % 60).' min' : $mins.' min' }})@endif
                        @unless($compact)
                            @if($visit->complaint_summary), {{ Str::limit($visit->complaint_summary, 60) }}@endif
                        @endunless
                    </div>
                </div>
                @if($visit->severity)
                    <x-ui.badge :color="$visit->severity_color" size="sm" class="d-none d-sm-inline-flex">{{ $visit->severity }}</x-ui.badge>
                @endif
                @if($canDischarge)
                <button type="button" class="btn btn-secondary btn-sm btn-discharge flex-shrink-0"
                        data-action="{{ route('patient-logs.discharge', $visit) }}"
                        data-name="{{ $visit->patient?->full_name }}"
                        data-disposition="{{ $visit->disposition }}"
                        aria-label="Discharge {{ $visit->patient?->full_name }}">
                    <x-ui.icon name="box-arrow-right" />Out
                </button>
                @endif
            </li>
            @endforeach
        </ul>
    </x-ui.card>
@endif

@if($canDischarge && $inClinic->isNotEmpty())
<x-ui.modal id="dischargeModal" title="Discharge patient" size="sm">
    <form method="POST" id="dischargeForm">
        @csrf @method('PATCH')
        <p class="mb-3">Record that <strong id="dischargeName"></strong> left the clinic now ({{ \App\Support\DisplayFormat::time(now()) }})?</p>
        <x-ui.select name="disposition" id="dischargeDisposition" label="Outcome" :options="\App\Models\PatientLog::dispositions()" />
        @if($dischargeSms)
            <div class="mt-3">
                <x-ui.checkbox name="notify_guardian" id="dischargeNotify" value="1" unchecked-value="0" checked
                    label="Text the guardian that the patient was discharged" />
            </div>
        @endif
    </form>
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="submit" form="dischargeForm" icon="box-arrow-right">Discharge</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

@push('scripts')
<script>
document.querySelectorAll('.btn-discharge').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('dischargeForm').action = this.dataset.action;
        document.getElementById('dischargeName').textContent = this.dataset.name || 'this patient';
        const sel = document.getElementById('dischargeDisposition');
        if (this.dataset.disposition && sel.querySelector('option[value="' + this.dataset.disposition + '"]')) {
            sel.value = this.dataset.disposition;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('dischargeModal')).show();
    });
});
</script>
@endpush
@endif
@endcan
