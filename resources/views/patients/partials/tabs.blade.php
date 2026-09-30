{{-- Patients module views: [Patients | Health forms]. Health forms only for staff who review intake. --}}
@can('review-intake')
    @php $pendingForms = $pendingForms ?? \App\Models\PatientIntakeSubmission::pending()->count(); @endphp
    <x-ui.tabs label="Patient views" :active="$active ?? 'patients'" :items="[
        'patients' => ['label' => 'Patients', 'href' => route('patients.index')],
        'intake'   => ['label' => 'Health forms', 'href' => route('patients.intake.index'), 'count' => $pendingForms ?: null],
    ]" />
@endcan
