@extends('layouts.app')

@section('title', 'Patients')

@php
    $canBulk = auth()->user()->canAny(['update-patients', 'delete-patients', 'export-patients']);
    $isArchived = ($filters['is_active'] ?? '') === 'archived';
    $exportQuery = array_filter($filters);
    $filtered = count(array_filter($filters)) > 0;
    $total = $patients->total();
    $statusOptions = ['1' => 'Active', '0' => 'Inactive', 'archived' => 'Archived'];
    $sexOptions = \App\Models\Patient::sexLabels();
    $levelOptions = array_combine($filterLists['levels'], $filterLists['levels']) ?: [];
    $sectionOptions = array_combine($filterLists['sections'], $filterLists['sections']) ?: [];
    $pendingForms = auth()->user()->can('review-intake') ? \App\Models\PatientIntakeSubmission::pending()->count() : 0;
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Patients"
        :description="number_format($total).' '.\Illuminate\Support\Str::plural('patient', $total).($filtered ? ' match the filters.' : ' on record.')"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => null]">
        <x-slot:actions>
            @canany(['update-patients', 'import-patients'])
                <x-ui.dropdown label="More" variant="secondary">
                    @can('update-patients')
                        <x-ui.dropdown-item :href="route('patients.promote.form')" icon="arrow-up-circle">Year-end promotion</x-ui.dropdown-item>
                    @endcan
                    @can('import-patients')
                        <x-ui.dropdown-item :href="route('patients.import.create')" icon="upload">Import from Excel</x-ui.dropdown-item>
                    @endcan
                </x-ui.dropdown>
            @endcanany
            @can('export-patients')
                <x-ui.dropdown label="Export" icon="download" variant="secondary">
                    <x-ui.dropdown-header>Export the filtered list</x-ui.dropdown-header>
                    <x-ui.dropdown-item :href="route('patients.export', $exportQuery + ['format' => 'xlsx'])" icon="file-earmark-spreadsheet">Excel (.xlsx)</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('patients.export', $exportQuery + ['format' => 'csv'])" icon="filetype-csv">CSV</x-ui.dropdown-item>
                </x-ui.dropdown>
            @endcan
            @can('create-patients')
                <x-ui.button :href="route('patients.create')" icon="person-plus">New patient</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('patients.partials.tabs', ['active' => 'patients'])

    @php
        $topCategories = array_slice($stats['categories'], 0, 2, true);
        $sexPair = array_slice($sexOptions, 0, 2, true);
    @endphp
    <x-ui.stat-cards :cols="3 + count($topCategories)">
        <x-ui.stat-card label="Total patients" :value="number_format($stats['total'])" tone="patients">
            @if (count($sexPair) === 2)
                @foreach ($sexPair as $sexKey => $sexLabel)
                    <span class="stat-meta-item"><span @class(['stat-dot', $loop->first ? 'tone-sky' : 'tone-rose'])></span>{{ number_format($stats['sexes'][$sexKey] ?? 0) }} {{ strtolower($sexLabel) }}</span>
                @endforeach
            @endif
        </x-ui.stat-card>
        <x-ui.stat-card label="Active" :value="number_format($stats['active'])" tone="patients" icon="person-check" :href="route('patients.index', ['is_active' => 1])">
            <span class="stat-meta-item"><span class="stat-dot tone-amber"></span>Inactive {{ number_format($stats['inactive']) }}</span>
            <span class="stat-meta-item"><span class="stat-dot tone-slate"></span>Archived {{ number_format($stats['archived']) }}</span>
        </x-ui.stat-card>
        @foreach ($topCategories as $catKey => $catTotal)
            <x-ui.stat-card :label="$categoryLabels[$catKey] ?? \Illuminate\Support\Str::headline((string) $catKey)" :value="number_format($catTotal)"
                tone="patients" icon="mortarboard" :href="route('patients.index', ['category' => $catKey])">
                {{ $stats['total'] ? round($catTotal / $stats['total'] * 100) : 0 }}% of current patients
            </x-ui.stat-card>
        @endforeach
        <x-ui.stat-card label="New this month" :value="number_format($stats['new_month'])" tone="patients" icon="person-plus">
            Added since {{ now()->startOfMonth()->format('M j') }}
        </x-ui.stat-card>
    </x-ui.stat-cards>

    <x-ui.filters :action="route('patients.index')" search-placeholder="Name, patient no. or student ID"
        :labels="['category' => 'Category', 'year_level' => 'Grade', 'section' => 'Section', 'sex' => 'Sex', 'is_active' => 'Status']"
        :options="['category' => $categoryLabels, 'sex' => $sexOptions, 'is_active' => $statusOptions]">
        <x-slot:inline>
            <x-ui.select name="category" size="sm" aria-label="Category" :options="$categoryLabels" placeholder="All categories"
                :selected="$filters['category'] ?? null" data-category-filter />
        </x-slot:inline>
        <x-ui.select name="year_level" id="flt-year" label="Grade / year level" size="sm" :options="$levelOptions" placeholder="All"
            :selected="$filters['year_level'] ?? null" data-reset-on-category />
        <x-ui.select name="section" id="flt-section" label="Section" size="sm" :options="$sectionOptions" placeholder="All"
            :selected="$filters['section'] ?? null" data-reset-on-category />
        <x-ui.select name="sex" id="flt-sex" label="Sex" size="sm" :options="$sexOptions" placeholder="All"
            :selected="$filters['sex'] ?? null" />
        <x-ui.select name="is_active" id="flt-status" label="Status" size="sm" :options="$statusOptions" placeholder="All"
            :selected="$filters['is_active'] ?? null" help="Archived shows records that were removed from the list." />
    </x-ui.filters>

    @if ($errors->any())
        <x-ui.alert variant="danger">{{ $errors->first() }}</x-ui.alert>
    @endif

    <x-ui.card flush>
        <x-ui.table :paginator="$patients" noun="patients" caption="Patients" responsive="stack">
            <x-slot:head>
                @if ($canBulk && $patients->isNotEmpty())
                    <x-ui.th width="44px">
                        <input type="checkbox" class="form-check-input" id="selectAll" aria-label="Select all patients on this page">
                    </x-ui.th>
                @endif
                <x-ui.th>Patient</x-ui.th>
                <x-ui.th priority="md">Category</x-ui.th>
                <x-ui.th priority="md">Grade and section</x-ui.th>
                <x-ui.th priority="xl">Sex</x-ui.th>
                <x-ui.th priority="lg">Age</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($patients as $patient)
                @php $academicText = collect([$patient->year_level, $patient->section])->filter()->implode(', '); @endphp
                <tr>
                    @if ($canBulk)
                        <td class="cell-check">
                            <input type="checkbox" class="form-check-input row-check" name="ids[]" value="{{ $patient->id }}" form="bulkForm"
                                   aria-label="Select {{ $patient->full_name }}">
                        </td>
                    @endif
                    <x-ui.td identity>
                        <div class="identity">
                            <x-ui.avatar :name="$patient->full_name" size="sm" />
                            <div class="identity-text">
                                <a href="{{ route('patients.show', $patient->id) }}" class="identity-title">{{ $patient->full_name }}</a>
                                <span class="identity-sub tabular">{{ $patient->patient_number }}@if ($patient->student_id), ID {{ $patient->student_id }}@endif</span>
                            </div>
                        </div>
                    </x-ui.td>
                    <x-ui.td priority="md" label="Category">
                        <x-ui.badge color="neutral" :dot="false">{{ $categoryLabels[$patient->category] ?? $patient->category }}</x-ui.badge>
                    </x-ui.td>
                    <x-ui.td priority="md" label="Grade and section" truncate>
                        @if ($academicText)
                            {{ $academicText }}@if ($patient->program_strand), {{ $patient->program_strand }}@endif
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td priority="xl" label="Sex">{{ $patient->sex_label }}</x-ui.td>
                    <x-ui.td priority="lg" label="Age" muted>{{ $patient->age_label }}</x-ui.td>
                    <x-ui.td label="Status">
                        @if ($patient->trashed())
                            <x-ui.badge color="neutral" icon="archive" :dot="false" title="This patient record has been archived">Archived</x-ui.badge>
                        @else
                            <x-ui.status-badge :status="(bool) $patient->is_active" type="patient" />
                        @endif
                    </x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$patient->full_name">
                            <x-ui.action-menu.item :href="route('patients.show', $patient->id)" icon="eye">View</x-ui.action-menu.item>
                            @if ($patient->trashed())
                                @can('restore-patients')
                                    <x-ui.action-menu.item :action="route('patients.restore', $patient->id)" method="PATCH" icon="arrow-counterclockwise">Restore</x-ui.action-menu.item>
                                @endcan
                            @else
                                @can('update-patients')
                                    <x-ui.action-menu.item :href="route('patients.edit', $patient)" icon="pencil">Edit</x-ui.action-menu.item>
                                @endcan
                                @can('create-patient-logs')
                                    <x-ui.action-menu.item :href="route('patient-logs.create', ['patient_id' => $patient->id])" icon="journal-plus">Log visit</x-ui.action-menu.item>
                                @endcan
                                @can('delete-patients')
                                    <x-ui.action-menu.divider />
                                    <x-ui.action-menu.item :action="route('patients.destroy', $patient)" method="DELETE" icon="archive" danger
                                        confirm="The record is hidden from the list and can be restored later. Clinic history is kept."
                                        :confirm-title="'Archive '.$patient->full_name.'?'" confirm-button="Archive">Archive</x-ui.action-menu.item>
                                @endcan
                            @endif
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($filtered)
                    <x-ui.empty-state icon="search" title="No patients match these filters" description="Try a different name or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('patients.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state module="patients" title="No patients yet" description="Add a patient or import your school list to get started." compact>
                        @can('create-patients')
                            <x-ui.button size="sm" icon="person-plus" :href="route('patients.create')">Add first patient</x-ui.button>
                        @endcan
                        @can('import-patients')
                            <x-ui.button size="sm" variant="secondary" icon="upload" :href="route('patients.import.create')">Import from Excel</x-ui.button>
                        @endcan
                    </x-ui.empty-state>
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>

    {{-- Bulk action bar (appears when rows are selected) --}}
    @if ($canBulk && $patients->isNotEmpty())
        <form method="POST" action="{{ route('patients.bulk') }}" id="bulkForm">
            @csrf
            <input type="hidden" name="action" id="bulkAction" value="">
            <input type="hidden" name="format" id="bulkFormat" value="">
        </form>

        <div class="bulk-bar" id="bulkBar" role="region" aria-label="Actions for selected patients" hidden>
            <strong class="bulk-bar-count"><span id="bulkCount">0</span> selected</strong>
            <div class="bulk-bar-actions">
                @if (! $isArchived)
                    @can('update-patients')
                        <x-ui.button size="sm" variant="secondary" icon="arrow-left-right" data-bs-toggle="modal" data-bs-target="#moveModal">Move or promote</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="check-circle" data-bulk="activate">Activate</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="slash-circle" data-bulk="deactivate">Deactivate</x-ui.button>
                    @endcan
                @endif
                @can('export-patients')
                    <x-ui.dropdown label="Export" icon="download" size="sm" variant="secondary">
                        <x-ui.dropdown-item icon="file-earmark-spreadsheet" data-bulk="export" data-format="xlsx">Excel (.xlsx)</x-ui.dropdown-item>
                        <x-ui.dropdown-item icon="filetype-csv" data-bulk="export" data-format="csv">CSV</x-ui.dropdown-item>
                    </x-ui.dropdown>
                @endcan
                @if (! $isArchived)
                    @can('delete-patients')
                        <x-ui.button size="sm" variant="secondary" icon="archive" class="text-danger" data-bulk="archive" data-bulk-confirm>Archive</x-ui.button>
                    @endcan
                @endif
            </div>
            <button type="button" class="btn btn-link btn-sm bulk-bar-clear" id="bulkClear">Clear selection</button>
        </div>
    @endif
</div>
@endsection

@if ($canBulk && $patients->isNotEmpty())
    @can('update-patients')
        @push('modals')
            <x-ui.modal id="moveModal" title="Move selected patients" subtitle="Only the fields you choose are changed.">
                <p class="small text-muted">Use this to move a class to a new section. To move a whole category one level up, use
                    <a href="{{ route('patients.promote.form') }}">Year-end promotion</a>.</p>
                <div class="row g-3" id="moveFields" data-academic="{{ json_encode($academic, JSON_UNESCAPED_UNICODE) }}">
                    <div class="col-12">
                        <x-ui.field label="Category" for="mv-category">
                            <select name="category" id="mv-category" class="form-select" form="bulkForm">
                                <option value="">Keep current category</option>
                                @foreach ($categoryLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                    @foreach (['year_level' => ['levels', 'Grade / year level'], 'section' => ['sections', 'Section'], 'program_strand' => ['programs', 'Program or strand']] as $name => [$key, $label])
                        <div class="col-12">
                            <x-ui.field :label="$label" :for="'mv-'.$name">
                                <select name="{{ $name }}" id="mv-{{ $name }}" class="form-select" form="bulkForm" data-list="{{ $key }}">
                                    <option value="">Keep current</option>
                                    @foreach ($academic['flat'][$key] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="clear_{{ $name }}" value="1" id="mv-clear-{{ $name }}" form="bulkForm">
                                <label class="form-check-label small" for="mv-clear-{{ $name }}">Clear {{ strtolower($label) }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <x-slot:footer>
                    <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
                    <x-ui.button icon="arrow-left-right" data-bulk="move">Move patients</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endpush
    @endcan
@endif

@push('scripts')
<script>
(function () {
    // Changing the category clears grade and section (their lists depend on it).
    const cat = document.querySelector('[data-category-filter]');
    if (cat) {
        cat.addEventListener('change', () => {
            cat.form.querySelectorAll('[data-reset-on-category]').forEach(e => e.value = '');
        });
    }

    const form = document.getElementById('bulkForm');
    if (!form) return;

    const bar = document.getElementById('bulkBar');
    const count = document.getElementById('bulkCount');
    const all = document.getElementById('selectAll');
    const checks = () => Array.from(document.querySelectorAll('.row-check'));
    const selected = () => checks().filter(c => c.checked).length;

    function refresh() {
        const n = selected();
        count.textContent = n;
        bar.hidden = n === 0;
        checks().forEach(c => c.closest('tr')?.classList.toggle('is-selected', c.checked));
        if (all) {
            all.checked = n > 0 && n === checks().length;
            all.indeterminate = n > 0 && n < checks().length;
        }
    }

    checks().forEach(c => c.addEventListener('change', refresh));
    if (all) all.addEventListener('change', () => { checks().forEach(c => c.checked = all.checked); refresh(); });
    document.getElementById('bulkClear').addEventListener('click', () => { checks().forEach(c => c.checked = false); refresh(); });

    document.querySelectorAll('[data-bulk]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (btn.hasAttribute('data-bulk-confirm') && window.confirmDialog) {
                const n = selected();
                const ok = await window.confirmDialog({
                    title: n === 1 ? 'Archive the selected patient?' : 'Archive ' + n + ' patients?',
                    message: 'They are hidden from the patient list. Their clinic history is kept and they can be restored from the Archived filter.',
                    variant: 'danger',
                    confirmText: 'Archive',
                    icon: 'archive',
                });
                if (!ok) return;
            }
            document.getElementById('bulkAction').value = btn.dataset.bulk;
            document.getElementById('bulkFormat').value = btn.dataset.format || '';
            if (btn.dataset.bulk !== 'export') {
                btn.disabled = true;
            }
            form.submit();
        });
    });

    // Move modal: grade / section / program follow the chosen category.
    const move = document.getElementById('moveFields');
    if (move) {
        let cfg = {};
        try { cfg = JSON.parse(move.dataset.academic || '{}'); } catch (e) {}
        const mvCat = document.getElementById('mv-category');
        mvCat.addEventListener('change', () => {
            const lists = (cfg.byCategory && cfg.byCategory[mvCat.value]) || cfg.flat || {};
            move.querySelectorAll('select[data-list]').forEach(sel => {
                const opts = lists[sel.dataset.list] || [];
                sel.innerHTML = '<option value="">Keep current</option>';
                opts.forEach(v => { const o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); });
            });
        });
    }

    refresh();
})();
</script>
@endpush
