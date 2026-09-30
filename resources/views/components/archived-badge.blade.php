@props(['patient' => null])
{{-- Shown next to a patient name when the patient record is archived (soft-deleted). --}}
@if($patient && method_exists($patient, 'trashed') && $patient->trashed())
<span {{ $attributes->merge(['class' => 'badge bg-secondary-subtle text-secondary-emphasis border ms-1 align-middle']) }}
      title="This patient record has been archived">
    <i class="bi bi-archive me-1"></i>Archived
</span>
@endif
