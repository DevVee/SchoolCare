@extends('layouts.app')

@section('title', 'Dashboard')

@use('App\Support\DisplayFormat')

@php
    $user = auth()->user();

    $canLogs  = $user->can('view-patient-logs');
    $canAppts = $user->can('view-appointments');
    $canMeds  = $user->can('view-medicines');
    $canAudit = $user->can('view-audit-logs');

    // Recent activity: audit "module" names mapped to the module tone map (config/ui.php).
    $activityModule = function (?string $module) {
        $key = str_replace('_', '-', (string) $module);
        $key = config('ui.module_aliases.'.$key) ?? $key;
        $key = match ($key) {
            'appointment-slots', 'specialist-visits' => 'appointments',
            'patient-intake' => 'patients',
            default => $key,
        };
        return config('ui.module_meta.'.$key) ? $key : 'admin';
    };

    // AI welcome (ServiceCo): the Ask box and Coco's brief replace the plain welcome when the assistant is on.
    $aiName  = trim((string) settings('ai_assistant_name')) ?: 'Coco';
    $showAi  = filter_var(settings('ai_enabled', true), FILTER_VALIDATE_BOOLEAN)
        && $user->can('use-ai-assistant') && Route::has('ai-assistant.index');
    $showBrief = $showAi && Route::has('dashboard.brief');
    $starters = array_values(array_filter([
        'What needs attention today?',
        $canMeds ? 'Which medicines should we reorder?' : null,
        $canLogs ? 'Summarise this week\'s visits' : null,
        'Write a notice to parents',
    ]));

    $showAppts = $canAppts;
    $showTrend = $trend !== null;
    $bottom    = array_filter(['reasons' => $canLogs, 'stock' => $canMeds, 'activity' => $canAudit]);
    $bottomCol = match (count($bottom)) { 1 => 'col-12', 2 => 'col-lg-6', default => 'col-lg-6 col-xl-4' };
@endphp

@section('content')

<x-ui.hero :subtitle="$showAi ? 'Ask '.$aiName.' anything, or start from what is happening today.' : 'Clinic visits, appointments and inventory at a glance.'" class="mb-4">
    @canany(['create-patient-logs', 'view-appointments'])
    <x-slot:actions>
        @can('create-patient-logs')
            <x-ui.button variant="hero" :size="$showBrief ? 'sm' : null" icon="journal-plus" :href="route('patient-logs.create')">Log a visit</x-ui.button>
        @endcan
        @can('view-appointments')
            <x-ui.button variant="hero-outline" :size="$showBrief ? 'sm' : null" icon="calendar-check" :href="route('appointments.index')">Appointments</x-ui.button>
        @endcan
    </x-slot:actions>
    @endcanany

    @if($showAi)
        @include('dashboard.partials.ask', ['aiName' => $aiName, 'starters' => $starters])
    @endif

    @if($showBrief)
        <x-slot:aside>
            @include('dashboard.partials.brief', ['aiName' => $aiName])
        </x-slot:aside>
    @endif
</x-ui.hero>

@php
    $cardCount = ($canLogs ? 2 : 0) + ($canAppts ? 1 : 0) + ($canMeds ? 1 : 0) + (isset($stats['patients_active']) ? 1 : 0);
@endphp
@if($cardCount)
<x-ui.stat-cards :cols="$cardCount" class="mb-4">
    @if($canLogs)
        <x-ui.stat-card label="Visits today" :value="$stats['visits_today']" tone="logbook" icon="journal-medical" :href="route('patient-logs.index')">
            <span class="stat-mark mark-up">+{{ number_format($stats['visits_week']) }}</span> this week
        </x-ui.stat-card>
        <x-ui.stat-card label="In clinic now" :value="$stats['in_clinic']" tone="logbook" icon="door-open" href="#inClinicPanel">
            Checked in, not yet out
        </x-ui.stat-card>
    @endif
    @if($canAppts)
        <x-ui.stat-card label="Pending appointments" :value="$stats['appointments_pending']" tone="appointments" icon="hourglass-split"
            :href="route('appointments.index', ['status' => 'pending'])">
            {{ number_format($stats['appointments_today']) }} scheduled today
        </x-ui.stat-card>
    @endif
    @if($canMeds)
        <x-ui.stat-card label="Low stock" :value="$stats['low_stock_medicines']" tone="inventory" icon="exclamation-triangle" :href="route('medicines.low-stock')">
            <span @class(['stat-mark mark-warn' => $stats['expiring_medicines'] > 0])>{{ number_format($stats['expiring_medicines']) }}</span>
            expiring within {{ \App\Models\Medicine::expiryWarningDays() }} days
        </x-ui.stat-card>
    @endif
    @isset($stats['patients_active'])
        <x-ui.stat-card label="Patients" :value="$stats['patients_active']" tone="patients" icon="people" :href="route('patients.index')">
            <span class="stat-meta-item"><x-ui.icon name="gender-male" class="tone-sky" />{{ number_format($stats['patients_male']) }} male</span>
            <span class="stat-meta-item"><x-ui.icon name="gender-female" class="tone-rose" />{{ number_format($stats['patients_female']) }} female</span>
        </x-ui.stat-card>
    @endisset
</x-ui.stat-cards>
@endif

{{-- Currently in clinic (discharge "Out" buttons); one quiet line when empty --}}
@include('patient-logs.partials.in-clinic', ['compact' => true])

@if($showTrend || $showAppts)
<div class="row g-4 mb-4">
    @if($showTrend)
    <div class="{{ $showAppts ? 'col-lg-7 col-xl-8' : 'col-12' }}">
        <x-ui.card module="logbook" icon="graph-up" title="Clinic visits" :subtitle="'Last '.$trend['days'].' days'" class="h-100">
            <x-ui.chart type="line" :series="$trend['series']" :categories="$trend['categories']" height="260"
                :empty="'No visits in the last '.$trend['days'].' days.'" />
        </x-ui.card>
    </div>
    @endif

    @if($showAppts)
    <div class="{{ $showTrend ? 'col-lg-5 col-xl-4' : 'col-12' }}">
        <x-ui.card flush module="appointments" title="Today's appointments" class="h-100">
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('appointments.today')">View all</x-ui.button>
            </x-slot:actions>
            @if($todayAppointments->isEmpty())
                <x-ui.empty-state compact module="appointments" title="No appointments today" description="Approved and pending appointments for today show here." />
            @else
                <ul class="dash-list">
                    @foreach($todayAppointments as $appt)
                    <li>
                        <a href="{{ route('appointments.show', $appt) }}" class="dash-row dash-row-link">
                            <span class="dash-row-time">{{ DisplayFormat::time($appt->appointment_time, '-') }}</span>
                            <span class="dash-row-main">
                                <span class="dash-row-title">{{ $appt->patient?->full_name ?? $appt->requester_name ?? 'Unknown patient' }}</span>
                                @if($appt->purpose)<span class="dash-row-sub">{{ $appt->purpose }}</span>@endif
                            </span>
                            <x-ui.status-badge :status="$appt->status" type="appointment" size="sm" />
                        </a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
    @endif
</div>
@endif

@if(count($bottom))
<div class="row g-4">
    @if($canLogs)
    <div class="{{ $bottomCol }}">
        <x-ui.card module="logbook" icon="clipboard2-pulse" title="Top reasons for visit" :subtitle="'This month ('.now()->format('F').')'" class="h-100">
            <x-ui.chart type="horizontal-bar"
                :series="[['name' => 'Visits', 'data' => $topReasons->pluck('total')->all()]]"
                :categories="$topReasons->pluck('reason')->all()"
                :height="max(160, 38 * $topReasons->count() + 40)"
                empty="No visits logged this month." />
        </x-ui.card>
    </div>
    @endif

    @if($canMeds)
    <div class="{{ $bottomCol }}">
        <x-ui.card flush module="inventory" icon="exclamation-triangle" title="Inventory alerts" class="h-100">
            <x-slot:actions>
                <x-ui.dropdown label="View" size="sm" variant="ghost">
                    <x-ui.dropdown-item :href="route('medicines.low-stock')" icon="box-seam">Low stock</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('medicines.expiring')" icon="calendar-x">Expiring soon</x-ui.dropdown-item>
                </x-ui.dropdown>
            </x-slot:actions>
            @if($inventoryAlerts->isEmpty())
                <x-ui.empty-state compact icon="check2-circle" tone="success" title="Stock is in good shape" description="No medicines are low or expiring soon." />
            @else
                <ul class="dash-list">
                    @foreach($inventoryAlerts as $med)
                    @php
                        [$status, $label] = match (true) {
                            $med->quantity == 0 => ['out_of_stock', 'Out of stock'],
                            $med->is_low_stock  => ['low_stock', $med->quantity.' '.$med->unit.' left'],
                            default             => ['expiring', 'Expires '.DisplayFormat::date($med->expiration_date)],
                        };
                    @endphp
                    <li>
                        <a href="{{ route('medicines.show', $med) }}" class="dash-row dash-row-link">
                            <x-ui.icon-chip module="medicines" size="sm" />
                            <span class="dash-row-main">
                                <span class="dash-row-title">{{ $med->name }}</span>
                                <span class="dash-row-sub">{{ $med->category->name ?? 'No category' }}</span>
                            </span>
                            <x-ui.status-badge :status="$status" type="stock" :label="$label" size="sm" />
                        </a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
    @endif

    @if($canAudit)
    <div class="{{ $bottomCol }}">
        <x-ui.card flush module="admin" icon="clock-history" title="Recent activity" class="h-100">
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('admin.audit-logs.index')">Activity log</x-ui.button>
            </x-slot:actions>
            @if($recentActivity->isEmpty())
                <x-ui.empty-state compact module="admin" icon="clock-history" title="No recent activity" />
            @else
                <ul class="dash-list">
                    @foreach($recentActivity as $log)
                    @php $mod = $activityModule($log->module); @endphp
                    <li class="dash-row">
                        <x-ui.icon-chip :module="$mod" :icon="$log->module === 'auth' ? 'person-check' : null" size="sm" />
                        <span class="dash-row-main">
                            <span class="dash-row-title fw-normal" title="{{ $log->description }}">{{ $log->description }}</span>
                            <span class="dash-row-sub">{{ $log->user_name ?: 'System' }}, {{ $log->created_at->diffForHumans() }}</span>
                        </span>
                    </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
    @endif
</div>
@endif

@endsection
