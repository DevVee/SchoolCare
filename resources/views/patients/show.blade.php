@extends('layouts.app')

@section('title', $patient->full_name . ' | Patient profile')

@php
    $categoryLabel = \App\Models\Patient::categoryLabels()[$patient->category] ?? $patient->category;
    $tabs = [
        'overview'      => ['label' => 'Overview'],
        'visits'        => ['label' => 'Clinic visits', 'count' => $history['clinic_visits']->count()],
        'consultations' => ['label' => 'Consultations', 'count' => $history['consultations']->count()],
        'appointments'  => ['label' => 'Appointments', 'count' => $history['appointments']->count()],
        'medicines'     => ['label' => 'Medicines', 'count' => $history['dispensing_records']->count()],
    ];
    $tab = array_key_exists(request('tab'), $tabs) ? request('tab') : 'overview';
    $academic = collect([$patient->year_level, $patient->section])->filter()->implode(', ');
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$patient->full_name"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), $patient->full_name => null]">
        <span class="font-ui tabular text-muted">{{ $patient->patient_number }}</span>
        <x-ui.badge color="neutral" :dot="false">{{ $categoryLabel }}</x-ui.badge>
        @if ($patient->trashed())
            <x-ui.badge color="neutral" icon="archive" :dot="false">Archived</x-ui.badge>
        @else
            <x-ui.status-badge :status="(bool) $patient->is_active" type="patient" />
        @endif
        <x-slot:actions>
            @if ($patient->trashed())
                <x-ui.dropdown label="More" variant="secondary">
                    <x-ui.dropdown-item :href="route('patients.health-report', $patient->id)" icon="clipboard2-data">Health report</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('patients.history', $patient->id)" icon="clock-history">Full history</x-ui.dropdown-item>
                </x-ui.dropdown>
                @can('restore-patients')
                    <form method="POST" action="{{ route('patients.restore', $patient->id) }}" class="d-inline">
                        @csrf @method('PATCH')
                        <x-ui.button type="submit" icon="arrow-counterclockwise">Restore patient</x-ui.button>
                    </form>
                @endcan
            @else
                <x-ui.dropdown label="More" variant="secondary">
                    @can('create-appointments')
                        <x-ui.dropdown-item :href="route('appointments.create', ['patient_id' => $patient->id])" icon="calendar-plus">Book appointment</x-ui.dropdown-item>
                    @endcan
                    <x-ui.dropdown-item :href="route('patients.health-report', $patient->id)" icon="clipboard2-data">Health report</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('patients.history', $patient->id)" icon="clock-history">Full history</x-ui.dropdown-item>
                    @can('delete-patients')
                        <x-ui.dropdown-divider />
                        <x-ui.dropdown-item :action="route('patients.destroy', $patient)" method="DELETE" icon="archive" tone="danger"
                            confirm="The record is hidden from the list and can be restored later. Clinic history is kept."
                            :confirm-title="'Archive '.$patient->full_name.'?'" confirm-button="Archive">Archive patient</x-ui.dropdown-item>
                    @endcan
                </x-ui.dropdown>
                @can('update-patients')
                    <x-ui.button variant="secondary" icon="pencil" :href="route('patients.edit', $patient)">Edit</x-ui.button>
                @endcan
                @can('create-patient-logs')
                    <x-ui.button icon="journal-plus" :href="route('patient-logs.create', ['patient_id' => $patient->id])">Log visit</x-ui.button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($patient->trashed())
        <x-ui.alert variant="neutral" icon="archive" title="This patient record is archived">
            It is hidden from the patient list and pickers. The clinic history below is kept.
            @can('restore-patients') Restore it to use it again.@endcan
        </x-ui.alert>
    @endif

    <div class="row g-3">
        {{-- Profile --}}
        <div class="col-lg-4">
            <x-ui.card>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <x-ui.avatar :name="$patient->full_name" size="xl" />
                    <div class="min-w-0">
                        <p class="fw-semibold font-ui mb-0 text-truncate">{{ $patient->full_name }}</p>
                        <p class="text-muted small mb-0">{{ $categoryLabel }}@if ($academic), {{ $academic }}@endif</p>
                    </div>
                </div>

                @if ($patient->allergies)
                    <x-ui.alert variant="warning" title="Allergies" class="mb-3">{{ $patient->allergies }}</x-ui.alert>
                @endif

                <x-ui.description-list layout="compact">
                    @if ($patient->student_id)
                        <x-ui.description-item label="Student ID"><span class="tabular">{{ $patient->student_id }}</span></x-ui.description-item>
                    @endif
                    <x-ui.description-item label="Sex">{{ $patient->sex_label }}</x-ui.description-item>
                    <x-ui.description-item label="Birthdate">{{ $patient->birthdate?->format('M d, Y') }}</x-ui.description-item>
                    <x-ui.description-item label="Age">{{ $patient->age !== null ? $patient->age.' years old' : '' }}</x-ui.description-item>
                    <x-ui.description-item label="Contact">{{ $patient->contact_number }}</x-ui.description-item>
                    @if ($patient->other_contact)
                        <x-ui.description-item label="Other contact">{{ $patient->other_contact }}</x-ui.description-item>
                    @endif
                    <x-ui.description-item label="Email" class="text-break">{{ $patient->email }}</x-ui.description-item>
                    <x-ui.description-item label="Address">{{ $patient->address }}</x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        </div>

        {{-- History --}}
        <div class="col-lg-8">
            <x-ui.card flush>
                <x-slot:header class="pb-0">
                    <x-ui.tabs :items="$tabs" :active="$tab" variant="underline" label="Patient record sections" />
                </x-slot:header>

                @if ($tab === 'overview')
                    <div class="p-3 p-md-4 vstack gap-4">
                        <section>
                            <h2 class="form-section-title mb-2">Health</h2>
                            <x-ui.description-list>
                                <x-ui.description-item label="Blood type">{{ $patient->blood_type }}</x-ui.description-item>
                                <x-ui.description-item label="Allergies" empty="None recorded">{{ $patient->allergies }}</x-ui.description-item>
                                <x-ui.description-item label="Medical conditions" empty="None recorded">{{ $patient->medical_conditions }}</x-ui.description-item>
                                <x-ui.description-item label="Current medications" empty="None recorded">{{ $patient->current_medications }}</x-ui.description-item>
                                <x-ui.description-item label="Pediatrician or family doctor">@if ($patient->pediatrician_name || $patient->pediatrician_contact){{ $patient->pediatrician_name ?? 'Name not recorded' }}@if ($patient->pediatrician_contact), {{ $patient->pediatrician_contact }}@endif @endif</x-ui.description-item>
                                @if ($patient->notes)
                                    <x-ui.description-item label="Notes">{{ $patient->notes }}</x-ui.description-item>
                                @endif
                            </x-ui.description-list>
                        </section>

                        <section>
                            <h2 class="form-section-title mb-2">School details</h2>
                            <x-ui.description-list :items="[
                                'Grade / year level' => $patient->year_level,
                                'Section' => $patient->section,
                                'Program or strand' => $patient->program_strand,
                            ]" />
                        </section>

                        <section>
                            <h2 class="form-section-title mb-2">Guardian or parent</h2>
                            <x-ui.description-list>
                                <x-ui.description-item label="Name">{{ $patient->guardian_name }}</x-ui.description-item>
                                <x-ui.description-item label="Relationship">{{ $patient->guardian_relationship }}</x-ui.description-item>
                                <x-ui.description-item label="Contact">{{ $patient->guardian_contact }}</x-ui.description-item>
                                @if ($patient->guardian_facebook)
                                    <x-ui.description-item label="Facebook" class="text-break">{{ $patient->guardian_facebook }}</x-ui.description-item>
                                @endif
                                <x-ui.description-item label="Address">{{ $patient->guardian_address }}</x-ui.description-item>
                            </x-ui.description-list>
                        </section>

                        <section>
                            <h2 class="form-section-title mb-2">Emergency contact</h2>
                            <x-ui.description-list :items="[
                                'Name' => $patient->emergency_contact_name,
                                'Number' => $patient->emergency_contact_number,
                            ]" />
                        </section>
                    </div>

                @elseif ($tab === 'visits')
                    <x-ui.table dense responsive="stack" caption="Clinic visits">
                        <x-slot:head>
                            <x-ui.th>Date</x-ui.th>
                            <x-ui.th priority="md">Time in</x-ui.th>
                            <x-ui.th>Complaint</x-ui.th>
                            <x-ui.th>Outcome</x-ui.th>
                            <x-ui.th priority="lg">Logged by</x-ui.th>
                            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                        </x-slot:head>
                        @foreach ($history['clinic_visits'] as $visit)
                            <tr>
                                <x-ui.td identity>
                                    @can('view-patient-logs')
                                        <a href="{{ route('patient-logs.show', $visit) }}" class="fw-semibold">{{ $visit->log_date->format('M d, Y') }}</a>
                                    @else
                                        <span class="fw-semibold">{{ $visit->log_date->format('M d, Y') }}</span>
                                    @endcan
                                </x-ui.td>
                                <x-ui.td priority="md" label="Time in" muted>{{ \Carbon\Carbon::parse($visit->time_in)->format('h:i A') }}</x-ui.td>
                                <x-ui.td label="Complaint" truncate>{{ $visit->complaint_summary }}@if ($visit->severity) ({{ $visit->severity }})@endif</x-ui.td>
                                <x-ui.td label="Outcome"><x-ui.badge :color="$visit->disposition_color">{{ $visit->disposition_label }}</x-ui.badge></x-ui.td>
                                <x-ui.td priority="lg" label="Logged by" muted>{{ $visit->loggedBy->name ?? '-' }}</x-ui.td>
                                <x-ui.td actions>
                                    @can('view-patient-logs')
                                        <x-ui.action-menu :for="'visit on '.$visit->log_date->format('M d, Y')">
                                            <x-ui.action-menu.item :href="route('patient-logs.show', $visit)" icon="eye">View visit</x-ui.action-menu.item>
                                        </x-ui.action-menu>
                                    @endcan
                                </x-ui.td>
                            </tr>
                        @endforeach
                        <x-slot:empty>
                            <x-ui.empty-state module="logbook" title="No clinic visits recorded" compact>
                                @can('create-patient-logs')
                                    @unless ($patient->trashed())
                                        <x-ui.button size="sm" icon="journal-plus" :href="route('patient-logs.create', ['patient_id' => $patient->id])">Log visit</x-ui.button>
                                    @endunless
                                @endcan
                            </x-ui.empty-state>
                        </x-slot:empty>
                    </x-ui.table>

                @elseif ($tab === 'consultations')
                    <x-ui.table dense responsive="stack" caption="Consultations">
                        <x-slot:head>
                            <x-ui.th>Date</x-ui.th>
                            <x-ui.th>Complaint</x-ui.th>
                            <x-ui.th priority="md">Nurse</x-ui.th>
                            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                        </x-slot:head>
                        @foreach ($history['consultations'] as $consult)
                            <tr>
                                <x-ui.td identity><a href="{{ route('consultations.show', $consult) }}" class="fw-semibold">{{ \Carbon\Carbon::parse($consult->visit_date)->format('M d, Y') }}</a></x-ui.td>
                                <x-ui.td label="Complaint" truncate>{{ $consult->chief_complaint }}</x-ui.td>
                                <x-ui.td priority="md" label="Nurse" muted>{{ $consult->nurse->name ?? '-' }}</x-ui.td>
                                <x-ui.td actions>
                                    <x-ui.action-menu :for="'consultation on '.\Carbon\Carbon::parse($consult->visit_date)->format('M d, Y')">
                                        <x-ui.action-menu.item :href="route('consultations.show', $consult)" icon="eye">View consultation</x-ui.action-menu.item>
                                    </x-ui.action-menu>
                                </x-ui.td>
                            </tr>
                        @endforeach
                        <x-slot:empty>
                            <x-ui.empty-state module="consultations" title="No consultations recorded" compact />
                        </x-slot:empty>
                    </x-ui.table>

                @elseif ($tab === 'appointments')
                    <x-ui.table dense responsive="stack" caption="Appointments">
                        <x-slot:head>
                            <x-ui.th>Date</x-ui.th>
                            <x-ui.th priority="md">Time</x-ui.th>
                            <x-ui.th>Purpose</x-ui.th>
                            <x-ui.th>Status</x-ui.th>
                            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                        </x-slot:head>
                        @foreach ($history['appointments'] as $appt)
                            <tr>
                                <x-ui.td identity><a href="{{ route('appointments.show', $appt) }}" class="fw-semibold">{{ \Carbon\Carbon::parse($appt->appointment_date)->format('M d, Y') }}</a></x-ui.td>
                                <x-ui.td priority="md" label="Time" muted>{{ \Carbon\Carbon::parse($appt->appointment_time)->format('h:i A') }}</x-ui.td>
                                <x-ui.td label="Purpose" truncate>{{ $appt->purpose }}</x-ui.td>
                                <x-ui.td label="Status"><x-ui.status-badge :status="$appt->status" type="appointment" /></x-ui.td>
                                <x-ui.td actions>
                                    <x-ui.action-menu :for="'appointment on '.\Carbon\Carbon::parse($appt->appointment_date)->format('M d, Y')">
                                        <x-ui.action-menu.item :href="route('appointments.show', $appt)" icon="eye">View appointment</x-ui.action-menu.item>
                                    </x-ui.action-menu>
                                </x-ui.td>
                            </tr>
                        @endforeach
                        <x-slot:empty>
                            <x-ui.empty-state module="appointments" title="No appointments recorded" compact>
                                @can('create-appointments')
                                    @unless ($patient->trashed())
                                        <x-ui.button size="sm" variant="secondary" icon="calendar-plus" :href="route('appointments.create', ['patient_id' => $patient->id])">Book appointment</x-ui.button>
                                    @endunless
                                @endcan
                            </x-ui.empty-state>
                        </x-slot:empty>
                    </x-ui.table>

                @else
                    <x-ui.table dense responsive="stack" caption="Medicines given">
                        <x-slot:head>
                            <x-ui.th>Date</x-ui.th>
                            <x-ui.th>Medicine</x-ui.th>
                            <x-ui.th align="end">Qty</x-ui.th>
                            <x-ui.th priority="md">Given by</x-ui.th>
                            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                        </x-slot:head>
                        @foreach ($history['dispensing_records'] as $rec)
                            <tr>
                                <x-ui.td identity><a href="{{ route('dispensing.show', $rec) }}" class="fw-semibold">{{ \Carbon\Carbon::parse($rec->dispensed_at)->format('M d, Y') }}</a></x-ui.td>
                                <x-ui.td label="Medicine" truncate>{{ $rec->medicine->name ?? '-' }}</x-ui.td>
                                <x-ui.td label="Qty" numeric>{{ $rec->quantity }}</x-ui.td>
                                <x-ui.td priority="md" label="Given by" muted>{{ $rec->dispensedBy->name ?? '-' }}</x-ui.td>
                                <x-ui.td actions>
                                    <x-ui.action-menu :for="'medicine given on '.\Carbon\Carbon::parse($rec->dispensed_at)->format('M d, Y')">
                                        <x-ui.action-menu.item :href="route('dispensing.show', $rec)" icon="eye">View record</x-ui.action-menu.item>
                                    </x-ui.action-menu>
                                </x-ui.td>
                            </tr>
                        @endforeach
                        <x-slot:empty>
                            <x-ui.empty-state module="dispensing" title="No medicines given yet" compact />
                        </x-slot:empty>
                    </x-ui.table>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
