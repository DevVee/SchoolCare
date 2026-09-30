@extends('layouts.app')

@section('title', 'Import preview')

@php
    $counts = $analysis['counts'];
    $canImport = ! $analysis['missing'] && $counts['valid'] > 0;
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Check the import" :description="$filename.': nothing has been saved yet.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), 'Import' => route('patients.import.create'), 'Preview' => null]" />

    @if ($analysis['missing'])
        <x-ui.alert variant="danger" title="The file is missing required columns">
            @foreach ($analysis['missing'] as $col)<code>{{ $col }}</code>@if (! $loop->last), @endif @endforeach.
            Add them to the first row and upload again.
        </x-ui.alert>
    @endif

    @if ($analysis['too_many'])
        <x-ui.alert variant="warning">Only the first {{ number_format(\App\Services\Patients\PatientImportService::MAX_ROWS) }} rows were read. Split the file and import the rest separately.</x-ui.alert>
    @endif

    @if ($analysis['unknown'])
        <x-ui.alert variant="neutral">
            These columns are not used and will be ignored:
            @foreach ($analysis['unknown'] as $col)<code>{{ $col }}</code>@if (! $loop->last), @endif @endforeach.
        </x-ui.alert>
    @endif

    @if (! $analysis['missing'])
        <x-ui.stat-cards cols="4">
            <x-ui.stat-card label="Rows read" :value="$counts['total']" tone="patients" icon="table" />
            <x-ui.stat-card label="Ready to import" :value="$counts['valid']" tone="success" icon="check2-circle" />
            <x-ui.stat-card label="With errors" :value="$counts['error']" :tone="$counts['error'] ? 'danger' : 'slate'" icon="exclamation-octagon">
                @if ($counts['error'])Skipped. Fix them in the file.@endif
            </x-ui.stat-card>
            <x-ui.stat-card label="Duplicates" :value="$counts['duplicate']" :tone="$counts['duplicate'] ? 'warning' : 'slate'" icon="files">
                @if ($counts['duplicate'])Already registered, skipped.@endif
            </x-ui.stat-card>
        </x-ui.stat-cards>

        <x-ui.card flush title="Rows">
            <x-slot:actions>
                <div class="nav nav-segmented" role="group" aria-label="Show rows">
                    <button type="button" class="nav-link active" data-show="all" aria-pressed="true">All</button>
                    <button type="button" class="nav-link" data-show="error" aria-pressed="false">Errors <span class="nav-count">{{ $counts['error'] }}</span></button>
                    <button type="button" class="nav-link" data-show="duplicate" aria-pressed="false">Duplicates <span class="nav-count">{{ $counts['duplicate'] }}</span></button>
                    <button type="button" class="nav-link" data-show="valid" aria-pressed="false">Ready <span class="nav-count">{{ $counts['valid'] }}</span></button>
                </div>
            </x-slot:actions>
            <x-ui.table dense max-height="60vh" caption="Rows in the file" id="previewTable">
                <x-slot:head>
                    <x-ui.th align="end" width="56px">Row</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th>Name</x-ui.th>
                    <x-ui.th priority="lg">Student ID</x-ui.th>
                    <x-ui.th priority="md">Category</x-ui.th>
                    <x-ui.th priority="lg">Grade and section</x-ui.th>
                    <x-ui.th priority="xl">Sex</x-ui.th>
                    <x-ui.th priority="xl">Birthdate</x-ui.th>
                    <x-ui.th>Problems</x-ui.th>
                </x-slot:head>
                @foreach ($analysis['rows'] as $row)
                    @php $d = $row['data']; @endphp
                    <tr data-status="{{ $row['status'] }}">
                        <x-ui.td numeric muted>{{ $row['line'] }}</x-ui.td>
                        <x-ui.td>
                            @if ($row['status'] === 'valid')
                                <x-ui.badge color="success">Ready</x-ui.badge>
                            @elseif ($row['status'] === 'duplicate')
                                <x-ui.badge color="warning">Duplicate</x-ui.badge>
                            @else
                                <x-ui.badge color="danger">Error</x-ui.badge>
                            @endif
                        </x-ui.td>
                        <x-ui.td truncate>{{ trim(($d['last_name'] ?? '').', '.($d['first_name'] ?? '').' '.($d['middle_name'] ?? ''), ', ') }}</x-ui.td>
                        <x-ui.td priority="lg">{{ $d['student_id'] ?? '' }}</x-ui.td>
                        <x-ui.td priority="md">{{ $categoryLabels[$d['category'] ?? ''] ?? ($d['category'] ?? '') }}</x-ui.td>
                        <x-ui.td priority="lg">{{ collect([$d['year_level'] ?? null, $d['section'] ?? null])->filter()->implode(', ') }}</x-ui.td>
                        <x-ui.td priority="xl">{{ $d['sex'] ?? '' }}</x-ui.td>
                        <x-ui.td priority="xl">{{ $d['birthdate'] ?? '' }}</x-ui.td>
                        <x-ui.td wrap class="small">
                            @foreach ($row['errors'] as $field => $message)
                                <div class="text-danger">{{ $message }}</div>
                            @endforeach
                            @if ($row['duplicate'])
                                <div class="text-warning-emphasis">{{ $row['duplicate'] }}</div>
                            @endif
                        </x-ui.td>
                    </tr>
                @endforeach
                <x-slot:empty><x-ui.empty-state compact icon="table" title="The file has no rows" description="Add one patient per row below the column names." /></x-slot:empty>
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.card>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <p class="small text-muted mb-0">
                @if ($canImport)
                    {{ $counts['valid'] }} {{ \Illuminate\Support\Str::plural('patient', $counts['valid']) }} will be added with new patient numbers.
                    @if ($counts['error'] + $counts['duplicate'])
                        {{ $counts['error'] + $counts['duplicate'] }} {{ \Illuminate\Support\Str::plural('row', $counts['error'] + $counts['duplicate']) }} will be skipped. You can fix them in the file and import them later.
                    @endif
                @else
                    There are no rows that can be imported. Fix the file and upload it again.
                @endif
            </p>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('patients.import.destroy') }}">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="secondary">Cancel</x-ui.button>
                </form>
                @if ($canImport)
                    <form method="POST" action="{{ route('patients.import.store') }}" id="confirmImport">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <x-ui.button type="submit" icon="check2-circle" id="confirmImportBtn">Import {{ $counts['valid'] }} {{ \Illuminate\Support\Str::plural('patient', $counts['valid']) }}</x-ui.button>
                    </form>
                @endif
            </div>
        </div>
    </x-ui.card>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-show]').forEach(btn => {
    btn.addEventListener('click', () => {
        const show = btn.dataset.show;
        document.querySelectorAll('#previewTable tbody tr[data-status]').forEach(tr => { tr.hidden = show !== 'all' && tr.dataset.status !== show; });
        document.querySelectorAll('[data-show]').forEach(b => {
            b.classList.toggle('active', b === btn);
            b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
        });
    });
});
const confirmForm = document.getElementById('confirmImport');
if (confirmForm) {
    confirmForm.addEventListener('submit', () => {
        const b = document.getElementById('confirmImportBtn');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Importing';
    });
}
</script>
@endpush
