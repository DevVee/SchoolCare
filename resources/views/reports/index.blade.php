@extends('layouts.app')

@section('title', 'Reports')

@php
    $groups = [
        'Clinic activity' => [
            ['route' => 'patient-logs.index', 'can' => 'view-patient-logs', 'module' => 'logbook', 'icon' => 'journal-medical', 'title' => 'Clinic logbook',
             'desc' => 'Every visit: who came in, why, what was done and the outcome.'],
            ['route' => 'reports.daily', 'module' => 'reports', 'icon' => 'calendar-day', 'title' => 'Daily report',
             'desc' => 'Visits, consultations, appointments and medicines for one day.'],
            ['route' => 'reports.monthly', 'module' => 'reports', 'icon' => 'calendar-month', 'title' => 'Monthly report',
             'desc' => 'Visits per day, top reasons, patient categories and stock alerts.'],
            ['route' => 'reports.annual', 'module' => 'reports', 'icon' => 'graph-up', 'title' => 'Annual report',
             'desc' => 'Visits, consultations and appointments month by month.'],
        ],
        'Medicines and appointments' => [
            ['route' => 'reports.medicine-usage', 'module' => 'medicines', 'icon' => 'capsule', 'title' => 'Medicine usage',
             'desc' => 'Most dispensed medicines for a date range.'],
            ['route' => 'reports.inventory', 'module' => 'inventory', 'icon' => 'box-seam', 'title' => 'Inventory snapshot',
             'desc' => 'Current stock levels, low stock and expiring medicines.'],
            ['route' => 'reports.appointments', 'module' => 'appointments', 'icon' => 'calendar-check', 'title' => 'Appointments report',
             'desc' => 'Appointments by status for a date range.'],
        ],
    ];
@endphp

@section('content')

<x-ui.page-header title="Reports" description="View clinic activity for a day, a month or a year, then print or export it." />

@include('reports.partials.stat-cards', ['cards' => [
    ['label' => 'Visits today', 'value' => $stats['visits_today'], 'module' => 'logbook', 'tone' => 'teal', 'icon' => 'calendar-day', 'href' => route('reports.daily')],
    ['label' => 'Visits this month', 'value' => $stats['visits_month'], 'module' => 'logbook', 'tone' => 'teal', 'icon' => 'calendar-month', 'href' => route('reports.monthly')],
    ['label' => 'Visits this year', 'value' => $stats['visits_year'], 'module' => 'logbook', 'tone' => 'teal', 'icon' => 'graph-up', 'href' => route('reports.annual')],
]])

<div class="row g-4">
    @foreach($groups as $groupTitle => $reports)
    <div class="col-lg-6">
        <x-ui.card flush :title="$groupTitle" class="h-100">
            <ul class="dash-list">
                @foreach($reports as $r)
                    @if(empty($r['can']) || auth()->user()->can($r['can']))
                    <li>
                        <a href="{{ route($r['route']) }}" class="dash-row dash-row-link">
                            <x-ui.icon-chip :module="$r['module']" :icon="$r['icon']" />
                            <span class="dash-row-main">
                                <span class="dash-row-title">{{ $r['title'] }}</span>
                                <span class="dash-row-sub">{{ $r['desc'] }}</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="text-muted" />
                        </a>
                    </li>
                    @endif
                @endforeach
            </ul>
        </x-ui.card>
    </div>
    @endforeach
</div>

@endsection
