@extends('layouts.app')

@section('title', 'Clinic Logbook')

@section('content')
@php
    $f          = $filters;
    $today      = today()->toDateString();
    $isToday    = $f['dateFrom'] === $today && $f['dateTo'] === $today;
    $singleDay  = $f['dateFrom'] === $f['dateTo'];
    $hasFilters = $f['search'] !== '' || $f['disposition'] || $f['severity'] || $f['reason'] || $f['category'] || $f['status'] || ! $isToday;
    $fromDate   = \Carbon\Carbon::parse($f['dateFrom']);
    $toDate     = \Carbon\Carbon::parse($f['dateTo']);
    $rangeLabel = $singleDay
        ? $fromDate->format('l, F d, Y')
        : $fromDate->format('M d, Y').' to '.$toDate->format('M d, Y');

    $dispositionLabels = \App\Models\PatientLog::dispositions();
    $categoryLabels    = \App\Models\Patient::categoryLabels();
    $severityOptions   = array_combine(\App\Models\PatientLog::severities(), \App\Models\PatientLog::severities()) ?: [];
    $reasonChoices     = array_combine(\App\Models\PatientLog::reasonOptions(), \App\Models\PatientLog::reasonOptions()) ?: [];
    $statusOptions     = ['in_clinic' => 'Still in clinic', 'discharged' => 'Discharged'];

    // Previous / next day (or period of the same length), keeping the other filters.
    $spanDays = (int) $fromDate->diffInDays($toDate) + 1;
    $shiftUrl = fn (int $dir) => request()->fullUrlWithQuery([
        'date' => null,
        'date_from' => $fromDate->copy()->addDays($dir * $spanDays)->toDateString(),
        'date_to' => $toDate->copy()->addDays($dir * $spanDays)->toDateString(),
        'page' => null,
    ]);
@endphp

<x-ui.page-header title="Clinic logbook" description="Daily visit log: who came in, why, and what was done.">
    @can('create-patient-logs')
    <x-slot:actions>
        <x-ui.button :href="route('patient-logs.create')" icon="plus-lg">Log a visit</x-ui.button>
    </x-slot:actions>
    @endcan
</x-ui.page-header>

<x-ui.tabs class="mb-3" label="Logbook views" active="logbook" :items="[
    'logbook'  => ['label' => 'Logbook', 'icon' => 'list-ul', 'href' => route('patient-logs.index', request()->query())],
    'calendar' => ['label' => 'Calendar', 'icon' => 'calendar3', 'href' => route('patient-logs.calendar', array_filter(['category' => $f['category'], 'month' => $fromDate->format('Y-m')]))],
]" />

{{-- Currently in clinic (Discharge buttons); one quiet line when empty --}}
@include('patient-logs.partials.in-clinic', ['inClinic' => $inClinic])

<div class="row g-3 mb-3">
    <div class="col-12 col-sm-4">
        <x-ui.stat-card class="h-100" label="Today" :value="$stats['today']" icon="calendar-day" tone="teal"
            :sub="$inClinic->count().' in clinic now'" :href="route('patient-logs.index')" />
    </div>
    <div class="col-6 col-sm-4">
        <x-ui.stat-card class="h-100" label="This week" :value="$stats['week']" icon="calendar-week" tone="teal"
            :sub="'Since '.today()->startOfWeek()->format('D, M j')"
            :href="route('patient-logs.index', ['date_from' => today()->startOfWeek()->toDateString(), 'date_to' => $today])" />
    </div>
    <div class="col-6 col-sm-4">
        <x-ui.stat-card class="h-100" label="This month" :value="$stats['month']" icon="calendar-month" tone="teal"
            :sub="today()->format('F Y')"
            :href="route('patient-logs.index', ['date_from' => today()->startOfMonth()->toDateString(), 'date_to' => $today])" />
    </div>
</div>

<x-ui.filters class="mb-3" :action="route('patient-logs.index')" search-placeholder="Name, patient no. or reason"
    :reset-url="route('patient-logs.index')"
    :labels="[
        'date' => 'Date',
        'date_from' => 'From',
        'date_to' => 'To',
        'severity' => 'Severity',
        'reason' => 'Reason',
        'category' => 'Category',
        'disposition' => 'Outcome',
        'status' => 'Status',
    ]"
    :options="[
        'date' => [(string) request('date') => $fromDate->format('M d, Y')],
        'date_from' => [(string) request('date_from') => $fromDate->format('M d, Y')],
        'date_to' => [(string) request('date_to') => $toDate->format('M d, Y')],
        'category' => $categoryLabels,
        'disposition' => $dispositionLabels,
        'status' => $statusOptions,
    ]">
    <x-slot:inline>
        <x-ui.date-range from-name="date_from" to-name="date_to" :from="$f['dateFrom']" :to="$f['dateTo']" :max="$today" label="Visit dates" />
    </x-slot:inline>
    <x-ui.select name="severity" label="Severity" size="sm" :options="$severityOptions" placeholder="Any severity" :selected="$f['severity']" />
    <x-ui.select name="reason" label="Reason" size="sm" :options="$reasonChoices" placeholder="Any reason" :selected="$f['reason']" />
    <x-ui.select name="category" label="Category" size="sm" :options="$categoryLabels" placeholder="All categories" :selected="$f['category']" />
    <x-ui.select name="disposition" label="Outcome" size="sm" :options="$dispositionLabels" placeholder="Any outcome" :selected="$f['disposition']" />
    <x-ui.select name="status" label="Status" size="sm" :options="$statusOptions" placeholder="Any status" :selected="$f['status']" />
</x-ui.filters>

<x-ui.card flush>
    <x-ui.table responsive="stack" :paginator="$logs" noun="visits" :caption="'Visits, '.$rangeLabel">
        <x-slot:toolbar>
            <div class="logbook-day">
                <x-ui.button variant="ghost" size="sm" icon="chevron-left" icon-only :label="$singleDay ? 'Previous day' : 'Previous period'" :href="$shiftUrl(-1)" />
                <h2 class="logbook-day-label fs-6 mb-0">{{ $rangeLabel }}</h2>
                <x-ui.button variant="ghost" size="sm" icon="chevron-right" icon-only :label="$singleDay ? 'Next day' : 'Next period'" :href="$shiftUrl(1)" :disabled="$toDate->gte(today())" />
            </div>
            <span class="text-muted small tabular">{{ $logs->total() }} {{ Str::plural('visit', $logs->total()) }}</span>
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th data-phone-sub>{{ $singleDay ? 'Time in' : 'Date and time' }}</x-ui.th>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th priority="md" data-phone-sub>Reasons</x-ui.th>
            <x-ui.th data-phone-right>Severity</x-ui.th>
            <x-ui.th priority="xl">Medicines</x-ui.th>
            <x-ui.th priority="lg">Outcome</x-ui.th>
            <x-ui.th priority="lg">Time out</x-ui.th>
            <x-ui.th priority="xl">SMS</x-ui.th>
            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
        </x-slot:head>

        @foreach ($logs as $log)
        @php $name = $log->patient?->full_name ?? 'Unknown patient'; @endphp
        <tr>
            <x-ui.td label="Time in" class="tabular">
                @unless($singleDay){{ $log->log_date->format('M d') }}, @endunless{{ \App\Support\DisplayFormat::time($log->time_in) }}
            </x-ui.td>
            <x-ui.td identity>
                <div class="identity">
                    <div class="identity-text">
                        <a href="{{ route('patient-logs.show', $log) }}" class="identity-title" title="View visit">{{ $name }}</a>
                        <span class="identity-sub">{{ collect([
                            $log->patient?->patient_number,
                            $log->patient?->category ? ($categoryLabels[$log->patient->category] ?? ucwords(str_replace('_', ' ', $log->patient->category))) : null,
                            trim(($log->patient?->year_level ?? '').' '.($log->patient?->section ?? '')),
                        ])->filter()->join(', ') }}<x-archived-badge :patient="$log->patient" /></span>
                    </div>
                </div>
            </x-ui.td>
            <x-ui.td label="Reasons" priority="md" truncate>{{ $log->complaint_summary ?: '-' }}@if($log->attachments_count) ({{ $log->attachments_count }} {{ Str::plural('photo', $log->attachments_count) }})@endif</x-ui.td>
            <x-ui.td label="Severity">
                @if($log->severity)
                    <x-ui.badge :color="$log->severity_color" size="sm">{{ $log->severity }}</x-ui.badge>
                @else
                    <span class="text-muted">-</span>
                @endif
            </x-ui.td>
            <x-ui.td label="Medicines" priority="xl" muted truncate>{{ $log->dispensingRecords->map(fn ($rec) => ($rec->medicine?->name ?? 'Removed medicine').' x'.$rec->quantity)->join(', ') ?: '-' }}</x-ui.td>
            <x-ui.td label="Outcome" priority="lg">
                <x-ui.badge :color="$log->disposition_color" size="sm">{{ $log->disposition_label }}</x-ui.badge>
            </x-ui.td>
            <x-ui.td label="Time out" priority="lg" class="tabular">
                @if($log->time_out)
                    {{ \App\Support\DisplayFormat::time($log->time_out) }}
                @elseif($log->log_date->isToday())
                    <x-ui.badge color="info" size="sm">In clinic</x-ui.badge>
                @else
                    <span class="text-muted">-</span>
                @endif
            </x-ui.td>
            <x-ui.td label="SMS" priority="xl">
                @if($log->sms_guardian)
                    @if($log->sms_sent)
                        <x-ui.badge color="success" size="sm" title="Visit text sent to the guardian">Sent</x-ui.badge>
                    @else
                        <x-ui.badge color="danger" size="sm" title="Visit text not sent. See the SMS log.">Not sent</x-ui.badge>
                    @endif
                @else
                    <span class="text-muted">-</span>
                @endif
            </x-ui.td>
            <x-ui.td actions>
                <x-ui.action-menu :label="'Actions for visit of '.$name">
                    <x-ui.action-menu.item :href="route('patient-logs.show', $log)" icon="eye">View visit</x-ui.action-menu.item>
                    @can('update-patient-logs')
                    <x-ui.action-menu.item :href="route('patient-logs.edit', $log)" icon="pencil">Edit</x-ui.action-menu.item>
                    @endcan
                    @can('delete-patient-logs')
                    <x-ui.action-menu.divider />
                    <x-ui.action-menu.item :action="route('patient-logs.destroy', $log)" method="DELETE" icon="trash" danger
                        confirm="Medicines already given stay deducted from stock."
                        :confirm-title="'Remove the log entry for '.$name.'?'" confirm-button="Remove entry">Remove</x-ui.action-menu.item>
                    @endcan
                </x-ui.action-menu>
            </x-ui.td>
        </tr>
        @endforeach

        <x-slot:empty>
            @if($hasFilters)
                <x-ui.empty-state compact icon="search" title="No visits match these filters" description="Try another date or clear the filters.">
                    <x-ui.button variant="secondary" size="sm" :href="route('patient-logs.index')">Clear filters</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state compact module="logbook" title="No visits logged today" description="Visits you log appear here.">
                    @can('create-patient-logs')
                    <x-ui.button size="sm" icon="plus-lg" :href="route('patient-logs.create')">Log a visit</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            @endif
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>
@endsection
