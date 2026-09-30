@extends('layouts.app')

@section('title', 'Import patients')

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Import patients"
        description="Add many patients at once from an Excel (.xlsx) or CSV file. You see a preview before anything is saved."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), 'Import' => null]">
        <x-slot:actions>
            <x-ui.dropdown label="Download template" icon="download" variant="secondary">
                <x-ui.dropdown-item :href="route('patients.import.template', ['format' => 'xlsx'])" icon="file-earmark-spreadsheet">Excel (.xlsx)</x-ui.dropdown-item>
                <x-ui.dropdown-item :href="route('patients.import.template', ['format' => 'csv'])" icon="filetype-csv">CSV</x-ui.dropdown-item>
            </x-ui.dropdown>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('patients.import.preview') }}" enctype="multipart/form-data" id="importForm">
                @csrf
                <x-ui.card>
                    <x-ui.section title="Upload the file" description="Download the template, fill in one patient per row, then upload it here. A file exported from the patient list can also be edited and imported again.">
                        <x-ui.field label="Excel or CSV file" name="file" for="importFile" required
                            :help="'Up to 5 MB and '.number_format($maxRows).' rows. Only the first sheet is read. The first row must hold the column names.'">
                            <input type="file" name="file" id="importFile" required
                                   accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                                   class="form-control @error('file') is-invalid @enderror" @error('file') aria-invalid="true" aria-describedby="importFile-error" @enderror>
                        </x-ui.field>
                    </x-ui.section>
                    <x-slot:footer class="justify-content-end">
                        <x-ui.button variant="secondary" :href="route('patients.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="eye" id="importSubmit">Check file and preview</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>
        </div>

        <div class="col-lg-5">
            <x-ui.card title="How the file is read" icon="info-circle" icon-tone="info">
                <div class="small vstack gap-2">
                    <p class="mb-0"><strong>Required columns:</strong>
                        @foreach ($required as $col)<code>{{ $col }}</code>@if (! $loop->last), @endif @endforeach.
                        Older column names such as <code>gender</code>, <code>grade_year</code> and <code>program_section</code> also work.</p>

                    <div>
                        <p class="mb-1"><strong>category</strong> accepts the value or the label:</p>
                        <ul class="mb-1 ps-3">
                            @foreach ($categoryLabels as $value => $label)
                                <li><code>{{ $value }}</code> or {{ $label }}</li>
                            @endforeach
                        </ul>
                        <p class="mb-0 text-muted">Names like Pre School, JHS, SHS and Faculty and Staff are matched automatically.</p>
                    </div>

                    <p class="mb-0"><strong>sex:</strong>
                        @foreach ($sexLabels as $value => $label)<code>{{ $value }}</code>@if (! $loop->last), @endif @endforeach
                        (or M / F).</p>
                    <p class="mb-0"><strong>birthdate:</strong> optional. Written as YYYY-MM-DD, MM/DD/YYYY or an Excel date.</p>
                    <p class="mb-0"><strong>year_level, section, program_strand:</strong> must be one of the choices for the category (Settings, Academic).</p>
                    <p class="mb-0"><strong>Duplicates</strong> (same student ID, or same name and birthdate as an existing patient or an earlier row) are shown and skipped.</p>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('importForm').addEventListener('submit', function () {
    const btn = document.getElementById('importSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Reading file';
});
</script>
@endpush
