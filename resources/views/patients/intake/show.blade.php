@extends('layouts.app')
@use('App\Support\DisplayFormat')

@section('title', 'Review health form')

@php
    $p = $submission->payload ?? [];
    $display = function ($field, $value) use ($categoryLabels, $sexLabels) {
        if ($value instanceof \DateTimeInterface) {
            return DisplayFormat::date($value);
        }
        if ($value === null || $value === '') {
            return '';
        }
        return match ($field) {
            'category'  => $categoryLabels[$value] ?? $value,
            'sex'       => $sexLabels[$value] ?? $value,
            'birthdate' => DisplayFormat::date($value),
            default     => (string) $value,
        };
    };
    $pending = $submission->status === 'pending';
    $hasMatches = $pending && $matches->isNotEmpty();
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$submission->full_name"
        :description="'Sent '.DisplayFormat::date($submission->created_at).' '.DisplayFormat::time($submission->created_at).'. Consent given '.($submission->consent_at ? DisplayFormat::date($submission->consent_at) : 'not recorded').'.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), 'Health forms' => route('patients.intake.index'), $submission->full_name => null]">
        <x-ui.badge :color="$submission->status_badge">{{ \App\Models\PatientIntakeSubmission::STATUSES[$submission->status] }}</x-ui.badge>
        @if ($pending)
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="x-circle" class="text-danger"
                    data-confirm="The family is not notified. The form stays in the Rejected list."
                    data-confirm-title="Reject this health form?" data-confirm-variant="danger" data-confirm-button="Reject form"
                    data-confirm-input="review_note" data-confirm-input-label="Reason (for example: duplicate of an existing record, test entry)"
                    :data-confirm-action="route('patients.intake.reject', $submission)" data-confirm-method="POST">Reject</x-ui.button>
                @can('create-patients')
                    <x-ui.button icon="person-plus"
                        :data-confirm="$hasMatches ? 'This creates a new record with a new patient number. Check the possible matches below first to avoid a duplicate.' : 'This creates a new patient record with a new patient number from the submitted information.'"
                        data-confirm-title="Create a new patient?" :data-confirm-variant="$hasMatches ? 'warning' : 'primary'" data-confirm-button="Create patient"
                        :data-confirm-action="route('patients.intake.approve', $submission)" data-confirm-method="POST">Approve as new patient</x-ui.button>
                @endcan
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="This form could not be saved">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            <div class="small mt-1">Approve it into a patient and edit the record afterwards, or reject the form with a note.</div>
        </x-ui.alert>
    @endif

    @unless ($pending)
        <x-ui.alert variant="neutral">
            {{ $submission->status === 'approved' ? 'Approved' : 'Rejected' }} by {{ $submission->reviewedBy?->name ?? 'a deleted user' }}
            on {{ DisplayFormat::date($submission->reviewed_at) }}.
            @if ($submission->review_note) Note: {{ $submission->review_note }}. @endif
            @if ($submission->patient)
                <a href="{{ route('patients.show', $submission->patient->id) }}">Open {{ $submission->patient->full_name }}</a>.
            @endif
        </x-ui.alert>
    @endunless

    <div class="row g-3">
        {{-- Submitted data --}}
        <div @class(['col-lg-5' => $hasMatches, 'col-12' => ! $hasMatches])>
            <x-ui.card title="Submitted information" module="patients" icon="clipboard2-heart">
                <x-ui.description-list :layout="$hasMatches ? 'compact' : 'grid'">
                    @foreach ($fields as $field => $label)
                        @php $text = $display($field, $p[$field] ?? null); @endphp
                        @continue($text === '')
                        <x-ui.description-item :label="$label">{{ $text }}</x-ui.description-item>
                    @endforeach
                </x-ui.description-list>
            </x-ui.card>
        </div>

        {{-- Merge into an existing patient --}}
        @if ($hasMatches)
            <div class="col-lg-7 vstack gap-3">
                @foreach ($matches as $match)
                    <form method="POST" action="{{ route('patients.intake.merge', $submission) }}">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $match->id }}">
                        <x-ui.card flush :title="'Possible match: '.$match->full_name"
                            :subtitle="$match->patient_number.($match->student_id ? ', ID '.$match->student_id : '')">
                            <x-slot:actions>
                                <x-ui.button size="sm" variant="ghost" icon-right="box-arrow-up-right" :href="route('patients.show', $match->id)" target="_blank" rel="noopener">Open record</x-ui.button>
                            </x-slot:actions>
                            <x-ui.table dense :caption="'Compare with '.$match->full_name">
                                <x-slot:head>
                                    <x-ui.th>Field</x-ui.th>
                                    <x-ui.th>On record</x-ui.th>
                                    <x-ui.th>Submitted</x-ui.th>
                                    <x-ui.th align="center">Use submitted</x-ui.th>
                                </x-slot:head>
                                @foreach ($fields as $field => $label)
                                    @php
                                        $new = $p[$field] ?? null;
                                        $old = $match->getAttribute($field);
                                        $newText = $display($field, $new);
                                        $oldText = $display($field, $old);
                                    @endphp
                                    @continue($newText === '' && $oldText === '')
                                    @php
                                        $same = $newText === $oldText;
                                        // Default: keep existing non-empty values, take submitted ones that fill a gap.
                                        $take = $newText !== '' && $oldText === '';
                                        $differs = ! $same && $newText !== '';
                                    @endphp
                                    <tr @class(['is-selected' => $differs])>
                                        <x-ui.td muted>{{ $label }}</x-ui.td>
                                        <x-ui.td truncate>{{ $oldText !== '' ? $oldText : 'Empty' }}</x-ui.td>
                                        <x-ui.td truncate @class(['fw-semibold' => $differs])>{{ $newText !== '' ? $newText : 'Empty' }}</x-ui.td>
                                        <x-ui.td align="center">
                                            @if ($differs)
                                                <input type="checkbox" class="form-check-input" name="take[]" value="{{ $field }}" @checked($take)
                                                       aria-label="Use the submitted {{ strtolower($label) }}">
                                            @else
                                                <span class="text-muted small">{{ $same ? 'Same' : '' }}</span>
                                            @endif
                                        </x-ui.td>
                                    </tr>
                                @endforeach
                            </x-ui.table>
                            <x-slot:footer class="justify-content-between flex-wrap gap-2">
                                <span class="small text-muted">Ticked fields replace what is on the record. The rest keep their current value.</span>
                                @can('update-patients')
                                    <x-ui.button type="submit" icon="person-check">Approve and update {{ $match->first_name }}</x-ui.button>
                                @endcan
                            </x-slot:footer>
                        </x-ui.card>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
