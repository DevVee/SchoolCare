@extends('layouts.app')
@use('App\Support\DisplayFormat')

@section('title', 'Appointment #' . $appointment->id)

@php
    $patient = $appointment->patient;
    $statusLabel = \App\Models\Appointment::statusLabels()[$appointment->status] ?? null;
    $canAct = ! $appointment->isCompleted() && ! $appointment->isCancelled();
    $who = $patient?->full_name ?? $appointment->requester_name;
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="'Appointment #'.$appointment->id"
        :description="($appointment->isOnlineRequest() ? 'Requested ' : 'Booked ').$appointment->created_at->diffForHumans().($appointment->createdBy ? ' by '.$appointment->createdBy->name : ($appointment->isOnlineRequest() ? ' by '.$appointment->requester_name : '')).'.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Appointments' => route('appointments.index'), '#'.$appointment->id => null]">
        <x-ui.status-badge :status="$appointment->status" type="appointment" :label="$statusLabel" />
        @if ($appointment->isOnlineRequest())
            <x-ui.badge color="info" icon="globe2" :dot="false">Online request</x-ui.badge>
        @endif
        <x-slot:actions>
            @if ($canAct)
                @canany(['update', 'complete', 'markNoShow', 'cancel'], $appointment)
                    <x-ui.dropdown label="More" variant="secondary">
                        @can('update', $appointment)
                            <x-ui.dropdown-item :href="route('appointments.edit', $appointment)" icon="pencil">Edit</x-ui.dropdown-item>
                        @endcan
                        @can('complete', $appointment)
                            @can('approve', $appointment)
                                {{-- Approve is the primary action, so completing lives here. --}}
                                <x-ui.dropdown-item :action="route('appointments.complete', $appointment)" method="PATCH" icon="check2-all">Mark completed</x-ui.dropdown-item>
                            @endcan
                        @endcan
                        @can('markNoShow', $appointment)
                            <x-ui.dropdown-item :action="route('appointments.no-show', $appointment)" method="PATCH" icon="person-slash"
                                confirm="The appointment is closed as a no-show." :confirm-title="'Mark '.$who.' as no-show?'" confirm-button="Mark no-show" confirm-variant="warning">Mark no-show</x-ui.dropdown-item>
                        @endcan
                        @can('cancel', $appointment)
                            <x-ui.dropdown-divider />
                            <x-ui.dropdown-item icon="x-circle" tone="danger" class="btn-cancel" :data-action="route('appointments.cancel', $appointment)">
                                {{ $appointment->isOnlineRequest() && $appointment->isPending() ? 'Decline request' : 'Cancel appointment' }}
                            </x-ui.dropdown-item>
                        @endcan
                    </x-ui.dropdown>
                @endcanany
            @else
                @can('update', $appointment)
                    <x-ui.button variant="secondary" icon="pencil" :href="route('appointments.edit', $appointment)">Edit</x-ui.button>
                @endcan
            @endif

            {{-- One primary action for the current step --}}
            @can('approve', $appointment)
                <form method="POST" action="{{ route('appointments.approve', $appointment) }}" class="d-inline">
                    @csrf @method('PATCH')
                    <x-ui.button type="submit" icon="check-circle">Approve</x-ui.button>
                </form>
            @else
                @can('complete', $appointment)
                    <form method="POST" action="{{ route('appointments.complete', $appointment) }}" class="d-inline">
                        @csrf @method('PATCH')
                        <x-ui.button type="submit" icon="check2-all">Mark completed</x-ui.button>
                    </form>
                @endcan
            @endcan
            @can('create-consultations')
                @if ($appointment->isApproved() && ! $appointment->consultation && $patient && ! $patient->trashed())
                    <x-ui.button variant="secondary" icon="clipboard-plus"
                        :href="route('consultations.create', ['appointment_id' => $appointment->id, 'patient_id' => $appointment->patient_id])">Start consultation</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($appointment->needsPatientLink() && ! $appointment->isTerminal())
        <x-ui.alert variant="warning" title="Not linked to a patient record yet.">
            Link this online request to an existing patient or create a new patient before approving it.
        </x-ui.alert>
    @elseif ($canAct && $appointment->isPending() && $appointment->needsPatientLink())
        <p class="small text-muted mb-0">Approve becomes available after the request is linked to a patient.</p>
    @endif

    <div class="row g-3">
        {{-- Left: Appointment details --}}
        <div class="col-lg-6">
            <x-ui.card title="Appointment details" module="appointments" class="h-100">
                <x-ui.description-list>
                    <x-ui.description-item label="Date">
                        <span class="fw-semibold">{{ DisplayFormat::date($appointment->appointment_date) }}</span>
                        <span class="text-muted small">({{ $appointment->appointment_date->diffForHumans() }})</span>
                    </x-ui.description-item>
                    <x-ui.description-item label="Time"><span class="fw-semibold">{{ DisplayFormat::time($appointment->appointment_time) }}</span></x-ui.description-item>
                    <x-ui.description-item label="Purpose">{{ $appointment->purpose }}</x-ui.description-item>
                    @if ($appointment->provider)
                        <x-ui.description-item label="With">{{ $appointment->provider }}</x-ui.description-item>
                    @endif
                    @if ($appointment->specialistVisit)
                        <x-ui.description-item label="Specialist visit">
                            @can('view-specialist-visits')
                                <a href="{{ route('specialist-visits.show', $appointment->specialistVisit) }}">{{ $appointment->specialistVisit->type }}: {{ $appointment->specialistVisit->specialist_name }}</a>
                            @else
                                {{ $appointment->specialistVisit->type }}: {{ $appointment->specialistVisit->specialist_name }}
                            @endcan
                            <div class="small text-muted">{{ $appointment->specialistVisit->time_range }}</div>
                        </x-ui.description-item>
                    @endif
                    @if ($appointment->notes)
                        <x-ui.description-item label="Notes">{{ $appointment->notes }}</x-ui.description-item>
                    @endif
                    @if ($appointment->isApproved() || $appointment->isCompleted())
                        <x-ui.description-item label="Approved by">{{ $appointment->approvedBy?->name ?? ($appointment->approved_at ? 'Deleted user' : '') }}</x-ui.description-item>
                        <x-ui.description-item label="Approved at">{{ $appointment->approved_at ? DisplayFormat::date($appointment->approved_at).' '.DisplayFormat::time($appointment->approved_at) : '' }}</x-ui.description-item>
                    @endif
                    @if ($appointment->isCancelled())
                        <x-ui.description-item label="Cancellation reason" class="text-danger">{{ $appointment->cancelled_reason ?: 'No reason given' }}</x-ui.description-item>
                    @endif
                </x-ui.description-list>
            </x-ui.card>
        </div>

        {{-- Right: Patient / requester --}}
        <div class="col-lg-6 vstack gap-3">
            @if ($appointment->isOnlineRequest())
                <x-ui.card title="Requested by" icon="globe2" icon-tone="info">
                    <x-ui.description-list layout="compact">
                        <x-ui.description-item label="Name"><span class="fw-semibold">{{ $appointment->requester_name }}</span></x-ui.description-item>
                        <x-ui.description-item label="Mobile">{{ $appointment->requester_contact }}</x-ui.description-item>
                        @if ($appointment->requester_email)
                            <x-ui.description-item label="Email" class="text-break">{{ $appointment->requester_email }}</x-ui.description-item>
                        @endif
                        @if ($appointment->requester_student_id)
                            <x-ui.description-item label="Student or employee ID"><span class="tabular">{{ $appointment->requester_student_id }}</span></x-ui.description-item>
                        @endif
                        @if ($appointment->requester_category_label)
                            <x-ui.description-item label="Category">{{ $appointment->requester_category_label }}</x-ui.description-item>
                        @endif
                        @if ($appointment->requester_school_line)
                            <x-ui.description-item label="Grade, program and section">{{ $appointment->requester_school_line }}</x-ui.description-item>
                        @endif
                    </x-ui.description-list>
                </x-ui.card>
            @endif

            @if ($patient)
                <x-ui.card title="Patient" module="patients">
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="ghost" :href="route('patients.show', $patient->id)">View profile</x-ui.button>
                    </x-slot:actions>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <x-ui.avatar :name="$patient->full_name" size="md" />
                        <div class="min-w-0">
                            <div class="fw-semibold">{{ $patient->full_name }}
                                @if ($patient->trashed())<x-ui.badge color="neutral" icon="archive" :dot="false" size="sm">Archived</x-ui.badge>@endif
                            </div>
                            <div class="small text-muted tabular">{{ $patient->patient_number }}</div>
                        </div>
                    </div>
                    <x-ui.description-list layout="compact">
                        <x-ui.description-item label="Category">{{ \App\Models\Patient::categoryLabels()[$patient->category] ?? $patient->category }}</x-ui.description-item>
                        <x-ui.description-item label="Age and sex">{{ $patient->age_label }}, {{ $patient->sex_label }}</x-ui.description-item>
                        <x-ui.description-item label="Contact">{{ $patient->contact_number ?? $patient->guardian_contact }}</x-ui.description-item>
                        @if ($patient->allergies)
                            <x-ui.description-item label="Allergies" class="text-danger">
                                <x-ui.icon name="exclamation-triangle-fill" class="me-1" />{{ $patient->allergies }}
                            </x-ui.description-item>
                        @endif
                    </x-ui.description-list>
                </x-ui.card>
            @elseif (! $appointment->isTerminal())
                {{-- Link an online request to a patient --}}
                <x-ui.card title="Link to a patient" icon="link-45deg" icon-tone="warning">
                    @can('update-appointments')
                        @if ($matches->isNotEmpty())
                            <p class="small text-muted mb-2">Patients that look like the requester (same student ID, mobile number or name):</p>
                            <ul class="list-unstyled vstack gap-2 mb-3">
                                @foreach ($matches as $m)
                                    <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded-2 px-3 py-2">
                                        <div class="small min-w-0">
                                            <div class="fw-semibold">{{ $m->full_name }}</div>
                                            <div class="text-muted">{{ $m->patient_number }}@if ($m->student_id), ID {{ $m->student_id }}@endif @if ($m->contact_number), {{ $m->contact_number }}@endif</div>
                                        </div>
                                        <form method="POST" action="{{ route('appointments.link-patient', $appointment) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="patient_id" value="{{ $m->id }}">
                                            <x-ui.button type="submit" size="sm" variant="secondary" icon="link-45deg">Link</x-ui.button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="small text-muted">No existing patient matches the requester's student ID, mobile number or name.</p>
                        @endif

                        <form method="POST" action="{{ route('appointments.link-patient', $appointment) }}" class="d-flex flex-wrap align-items-end gap-2 mb-3">
                            @csrf @method('PATCH')
                            <x-ui.field label="Or pick any patient" name="patient_id" for="linkPatientId" class="flex-grow-1">
                                <select name="patient_id" id="linkPatientId" class="form-select @error('patient_id') is-invalid @enderror" required>
                                    <option value="">Select a patient</option>
                                    @foreach (\App\Models\Patient::active()->orderBy('last_name')->orderBy('first_name')->limit(2000)->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'patient_number']) as $opt)
                                        <option value="{{ $opt->id }}">{{ $opt->last_name }}, {{ $opt->first_name }} ({{ $opt->patient_number }})</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.button type="submit">Link</x-ui.button>
                        </form>

                        @can('create-patients')
                            <x-ui.button size="sm" variant="secondary" icon="person-plus" :href="route('patients.create', ['from_appointment' => $appointment->id])">Create a new patient from this request</x-ui.button>
                        @endcan
                    @else
                        <p class="small text-muted mb-0">A staff member who can edit appointments needs to link this request to a patient.</p>
                    @endcan
                </x-ui.card>
            @endif

            {{-- Linked consultation --}}
            @if ($appointment->consultation)
                <x-ui.card title="Linked consultation" module="consultations">
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="ghost" :href="route('consultations.show', $appointment->consultation)">View</x-ui.button>
                    </x-slot:actions>
                    <p class="mb-0 small">{{ \Illuminate\Support\Str::limit($appointment->consultation->chief_complaint, 120) }}</p>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>

@include('appointments.partials.cancel-modal')
@endsection
