@extends('layouts.app')

@section('title', 'Visit Log: ' . ($log->patient?->full_name ?? 'Unknown patient'))

@section('content')
@use('App\Support\DisplayFormat')
@php
    $patient     = $log->patient;
    $name        = $patient?->full_name ?? 'Unknown patient';
    $inClinic    = $log->time_out === null;
    $canUpdate   = auth()->user()->can('update-patient-logs');
    $canCreate   = auth()->user()->can('create-patient-logs');
    $canDelete   = auth()->user()->can('delete-patient-logs');
    $maxPhotos   = \App\Models\PatientLog::MAX_ATTACHMENTS;
    $remaining   = $maxPhotos - $log->attachments->count();
    $categoryLabels = \App\Models\Patient::categoryLabels();
    $vitalFields = [
        'temperature'    => ['Temperature', ' °C'],
        'blood_pressure' => ['Blood pressure', ''],
        'pulse'          => ['Pulse', ' bpm'],
        'weight'         => ['Weight', ' kg'],
        'height'         => ['Height', ' cm'],
    ];
    $vitals = collect($vitalFields)->filter(fn ($f, $key) => ! empty($log->vital_signs[$key] ?? null));
@endphp

<x-ui.page-header :title="$name"
    :description="'Clinic visit, '.$log->log_date->format('l').', '.DisplayFormat::date($log->log_date).' at '.DisplayFormat::time($log->time_in)"
    :breadcrumbs="['Clinic logbook' => route('patient-logs.index', ['date' => $log->log_date->toDateString()]), 'Visit' => null]">
    <x-archived-badge :patient="$patient" class="ms-0" />
    @if($canUpdate || $canCreate || $canDelete)
    <x-slot:actions>
        @if($canUpdate && $inClinic)
            <x-ui.button icon="box-arrow-right" data-bs-toggle="modal" data-bs-target="#showDischargeModal">Discharge</x-ui.button>
        @endif
        @if($canUpdate)
            <x-ui.button :variant="$inClinic ? 'secondary' : 'primary'" icon="pencil" :href="route('patient-logs.edit', $log)">Edit</x-ui.button>
        @endif
        @if($canCreate || $canDelete)
        <x-ui.dropdown label="More" variant="secondary">
            @if($canCreate)
                <x-ui.dropdown-item :href="route('patient-logs.create', ['patient_id' => $log->patient_id])" icon="journal-plus">Log another visit for this patient</x-ui.dropdown-item>
            @endif
            @if($canDelete)
                @if($canCreate)<x-ui.dropdown-divider />@endif
                <x-ui.dropdown-item :action="route('patient-logs.destroy', $log)" method="DELETE" icon="trash" tone="danger"
                    confirm="Medicines already given stay deducted from stock."
                    :confirm-title="'Remove the log entry for '.$name.'?'" confirm-button="Remove entry">Remove entry</x-ui.dropdown-item>
            @endif
        </x-ui.dropdown>
        @endif
    </x-slot:actions>
    @endif
</x-ui.page-header>

<div class="row g-4">

    {{-- Patient, guardian, time --}}
    <div class="col-lg-4">
        <div class="vstack gap-4">
            <x-ui.card title="Patient">
                <div class="identity mb-3">
                    <x-ui.avatar :name="$name" size="md" />
                    <div class="identity-text">
                        <span class="identity-title">{{ $name }}</span>
                        <span class="identity-sub">{{ $patient?->patient_number }}</span>
                    </div>
                </div>
                <x-ui.description-list layout="compact">
                    <x-ui.description-item label="Category">{{ $patient?->category ? ($categoryLabels[$patient->category] ?? ucwords(str_replace('_', ' ', $patient->category))) : '' }}</x-ui.description-item>
                    <x-ui.description-item label="Grade and section">{{ trim(($patient?->year_level ?? '').' '.($patient?->section ?? '')) }}</x-ui.description-item>
                    <x-ui.description-item label="Guardian">{{ $patient?->guardian_name }}@if($patient?->guardian_relationship) ({{ $patient->guardian_relationship }})@endif</x-ui.description-item>
                    <x-ui.description-item label="Guardian contact">{{ $patient?->guardian_contact }}</x-ui.description-item>
                    <x-ui.description-item label="Visit text">
                        @if($log->sms_guardian)
                            @if($log->sms_sent)
                                <x-ui.badge color="success" size="sm">Sent</x-ui.badge>
                            @else
                                <x-ui.badge color="danger" size="sm">Not sent</x-ui.badge>
                                <span class="d-block text-muted small mt-1">See the SMS log for the reason.</span>
                            @endif
                        @else
                            <span class="text-muted">No text sent</span>
                        @endif
                    </x-ui.description-item>
                </x-ui.description-list>
                @if($patient)
                @can('view-patients')
                <x-slot:footer>
                    <x-ui.button variant="secondary" size="sm" icon="person-lines-fill" :href="route('patients.show', $patient->id)">View patient record</x-ui.button>
                </x-slot:footer>
                @endcan
                @endif
            </x-ui.card>

            <x-ui.card title="Visit">
                <x-ui.description-list layout="compact">
                    <x-ui.description-item label="Date">{{ DisplayFormat::date($log->log_date) }}</x-ui.description-item>
                    <x-ui.description-item label="Time in">{{ DisplayFormat::time($log->time_in) }}</x-ui.description-item>
                    <x-ui.description-item label="Time out">
                        @if($log->time_out)
                            {{ DisplayFormat::time($log->time_out) }}
                        @else
                            <x-ui.badge color="info" size="sm">Still in clinic</x-ui.badge>
                        @endif
                    </x-ui.description-item>
                    @if($log->time_out)
                    <x-ui.description-item label="Duration">{{ (int) \Carbon\Carbon::parse($log->time_in)->diffInMinutes(\Carbon\Carbon::parse($log->time_out)) }} min</x-ui.description-item>
                    @endif
                    <x-ui.description-item label="Outcome">
                        <x-ui.badge :color="$log->disposition_color" size="sm">{{ $log->disposition_label }}</x-ui.badge>
                    </x-ui.description-item>
                    <x-ui.description-item label="Logged by">{{ $log->loggedBy->name ?? 'Deleted user' }}</x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        </div>
    </div>

    {{-- Visit details --}}
    <div class="col-lg-8">
        <div class="vstack gap-4">

            {{-- Reason and severity --}}
            <x-ui.card title="Reason for visit">
                @if($log->severity)
                <x-slot:actions>
                    <x-ui.badge :color="$log->severity_color">{{ $log->severity }}</x-ui.badge>
                </x-slot:actions>
                @endif
                @if($log->reason_list)
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach((array) $log->reasons as $r)
                        <x-ui.badge color="neutral" :dot="false">{{ $r }}</x-ui.badge>
                    @endforeach
                    @if($log->other_reason)
                        <x-ui.badge color="neutral" :dot="false">Other: {{ $log->other_reason }}</x-ui.badge>
                    @endif
                </div>
                @endif
                @if($log->chief_complaint)
                    <x-ui.description-list layout="stacked" :items="['Complaint details' => $log->chief_complaint]" />
                @elseif(! $log->reason_list)
                    <p class="text-muted mb-0">No reason recorded.</p>
                @endif
            </x-ui.card>

            {{-- Vitals --}}
            @if($vitals->isNotEmpty())
            <x-ui.card title="Vital signs">
                <x-ui.description-list :items="$vitals->mapWithKeys(fn ($f, $key) => [$f[0] => $log->vital_signs[$key].$f[1]])->all()" />
            </x-ui.card>
            @endif

            {{-- Assessment, treatment, notes --}}
            @if($log->assessment || $log->treatment || $log->notes)
            <x-ui.card title="Assessment and treatment">
                <x-ui.description-list layout="stacked">
                    @if($log->assessment)<x-ui.description-item label="Assessment">{{ $log->assessment }}</x-ui.description-item>@endif
                    @if($log->treatment)<x-ui.description-item label="Treatment or action taken">{{ $log->treatment }}</x-ui.description-item>@endif
                    @if($log->notes)<x-ui.description-item label="Additional notes">{{ $log->notes }}</x-ui.description-item>@endif
                </x-ui.description-list>
            </x-ui.card>
            @endif

            {{-- Medicines given --}}
            <x-ui.card title="Medicines given" flush>
                <x-ui.table dense responsive="stack" caption="Medicines given during this visit">
                    <x-slot:head>
                        <x-ui.th>Medicine</x-ui.th>
                        <x-ui.th align="end">Quantity</x-ui.th>
                        <x-ui.th priority="md">Batch used</x-ui.th>
                        <x-ui.th priority="lg">Given by</x-ui.th>
                    </x-slot:head>
                    @foreach($log->dispensingRecords as $rec)
                    <tr>
                        <x-ui.td identity>
                            @can('view-medicines')
                                <a href="{{ route('medicines.show', $rec->medicine_id) }}" class="cell-title text-decoration-none">{{ $rec->medicine?->name ?? 'Removed medicine' }}</a>
                            @else
                                <span class="cell-title">{{ $rec->medicine?->name ?? 'Removed medicine' }}</span>
                            @endcan
                        </x-ui.td>
                        <x-ui.td label="Quantity" numeric>{{ $rec->quantity }} {{ $rec->medicine?->unit }}(s)</x-ui.td>
                        <x-ui.td label="Batch used" priority="md" muted wrap>
                            @forelse($rec->transactions as $t)
                                <div>{{ $t->batch_number ?: 'No batch no.' }}@if($t->expiration_date), exp. {{ DisplayFormat::date($t->expiration_date) }}@endif ({{ abs($t->quantity) }})</div>
                            @empty
                                -
                            @endforelse
                        </x-ui.td>
                        <x-ui.td label="Given by" priority="lg">{{ $rec->dispensedBy?->name ?? 'Deleted user' }}</x-ui.td>
                    </tr>
                    @endforeach
                    <x-slot:empty>
                        <x-ui.empty-state quiet icon="capsule" title="No medicine was given during this visit." class="px-3" />
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>

            {{-- Photos --}}
            <x-ui.card title="Photos" id="photos" description="Private. Only staff who can view the logbook can see them.">
                <x-slot:actions>
                    <span class="text-muted small tabular">{{ $log->attachments->count() }} of {{ $maxPhotos }}</span>
                </x-slot:actions>

                @if($log->attachments->isEmpty())
                    <x-ui.empty-state quiet icon="images" title="No photos attached." />
                @else
                <div class="row g-3 mb-3">
                    @foreach($log->attachments as $photo)
                    @php $url = route('patient-logs.attachments.show', [$log, $photo]); @endphp
                    <div class="col-6 col-md-4">
                        <div class="border rounded overflow-hidden h-100 d-flex flex-column">
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="d-block bg-surface-2" style="aspect-ratio:4/3;">
                                <img src="{{ $url }}" alt="Visit photo {{ $loop->iteration }}" loading="lazy"
                                     width="320" height="240" style="width:100%;height:100%;object-fit:cover;">
                            </a>
                            <div class="p-2 small d-flex align-items-center justify-content-between gap-2 mt-auto">
                                <span class="text-muted text-truncate">{{ $photo->created_at->format('M d') }}, {{ DisplayFormat::time($photo->created_at) }}, {{ $photo->size_label }}</span>
                                @if($canUpdate)
                                <form method="POST" action="{{ route('patient-logs.attachments.destroy', [$log, $photo]) }}"
                                      data-confirm="This cannot be undone." data-confirm-title="Delete this photo?" data-confirm-button="Delete photo">
                                    @csrf @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost" size="sm" icon="trash" icon-only :label="'Delete photo '.$loop->iteration" />
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($canUpdate && $remaining > 0)
                <form method="POST" action="{{ route('patient-logs.attachments.store', $log) }}" enctype="multipart/form-data" class="row g-2 align-items-end mt-1">
                    @csrf
                    @php $photoError = $errors->first('photos') ?: $errors->first('photos.*'); @endphp
                    <div class="col-sm-8">
                        <label for="photosInput" class="form-label">Add photos</label>
                        <input type="file" name="photos[]" id="photosInput" multiple accept="image/jpeg,image/png,image/webp"
                               @class(['form-control', 'is-invalid' => $photoError])
                               aria-describedby="{{ $photoError ? 'photosInput-error' : 'photosInput-help' }}">
                        @if($photoError)
                            <div class="invalid-feedback d-flex" id="photosInput-error"><x-ui.icon name="exclamation-circle" /><span>{{ $photoError }}</span></div>
                        @else
                            <div class="form-text" id="photosInput-help">JPG, PNG or WebP, up to 5 MB each. {{ $remaining }} more allowed.</div>
                        @endif
                    </div>
                    <div class="col-sm-4">
                        <x-ui.button type="submit" variant="secondary" icon="upload" block>Upload</x-ui.button>
                    </div>
                </form>
                @endif
            </x-ui.card>

        </div>
    </div>
</div>

@if($canUpdate && $inClinic)
<x-ui.modal id="showDischargeModal" title="Discharge patient" :action="route('patient-logs.discharge', $log)" method="PATCH" sheet>
    <p class="mb-3">Record that <strong>{{ $name }}</strong> left the clinic now?</p>
    <x-ui.select name="disposition" id="showDischargeDisposition" label="Outcome"
        :options="\App\Models\PatientLog::dispositions()" :selected="$log->disposition" />
    @if(settings('sms_enabled') && settings('notify_sms_clinic_discharge', true))
        <div class="mt-3">
            <x-ui.checkbox name="notify_guardian" id="showDischargeNotify" unchecked-value="0" checked
                label="Text the guardian that the patient was discharged" />
        </div>
    @endif
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="submit" icon="box-arrow-right">Discharge</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
@endif
@endsection
