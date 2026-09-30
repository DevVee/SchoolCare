{{--
    Report "Export" dropdown (PDF, CSV, Print). Shown only with the export-reports permission.
    @include('reports.partials.export', ['type' => 'daily', 'params' => ['date' => $date]])
--}}
@can('export-reports')
    @php $exportParams = ['type' => $type] + ($params ?? []); @endphp
    <x-ui.dropdown label="Export" icon="download" variant="secondary">
        <x-ui.dropdown-item :href="route('reports.export', $exportParams + ['format' => 'pdf'])" icon="filetype-pdf">PDF document</x-ui.dropdown-item>
        <x-ui.dropdown-item :href="route('reports.export', $exportParams + ['format' => 'csv'])" icon="filetype-csv">Spreadsheet (CSV)</x-ui.dropdown-item>
        <x-ui.dropdown-divider />
        <x-ui.dropdown-item icon="printer" onclick="window.print()">Print this page</x-ui.dropdown-item>
    </x-ui.dropdown>
@endcan
