@props(['patient' => null, 'class' => 'text-decoration-none fw-semibold'])
{{-- Null-safe patient name link that also works for archived (soft-deleted) patients. --}}
@if($patient)
    @can('view-patients')
    <a href="{{ route('patients.show', $patient->id) }}" class="{{ $class }}">{{ $patient->full_name }}</a>
    @else
    <span class="{{ $class }}">{{ $patient->full_name }}</span>
    @endcan
    <x-archived-badge :patient="$patient" />
@else
    <span class="text-muted fst-italic">Unknown patient</span>
@endif
