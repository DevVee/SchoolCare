@extends('layouts.app')

@section('title', 'Medicine usage')

@use('App\Support\DisplayFormat')

@php
    $top = $usage->take(10);
@endphp

@section('content')

<x-ui.page-header title="Medicine usage" :description="DisplayFormat::date($from).' to '.DisplayFormat::date($to)"
    :breadcrumbs="['Reports' => route('reports.index'), 'Medicine usage' => null]">
    <x-slot:actions>
        <form method="GET" action="{{ route('reports.medicine-usage') }}" class="report-period">
            <x-ui.input type="date" name="from" :value="$from" aria-label="From date" />
            <span class="report-period-sep">to</span>
            <x-ui.input type="date" name="to" :value="$to" aria-label="To date" />
            <x-ui.button type="submit" variant="secondary">Show</x-ui.button>
        </form>
        @include('reports.partials.export', ['type' => 'medicine-usage', 'params' => ['from' => $from, 'to' => $to]])
    </x-slot:actions>
</x-ui.page-header>

@include('reports.partials.stat-cards', ['cards' => [
    ['label' => 'Units dispensed', 'value' => $totalDispensed, 'module' => 'dispensing', 'tone' => 'success', 'icon' => 'prescription2'],
    ['label' => 'Medicines used', 'value' => $usage->count(), 'module' => 'medicines', 'tone' => 'orange', 'icon' => 'capsule'],
    ['label' => 'Times dispensed', 'value' => $usage->sum('times_dispensed'), 'module' => 'dispensing', 'tone' => 'success', 'icon' => 'arrow-repeat'],
]])

@if($usage->isNotEmpty())
<x-ui.card class="mb-4" module="medicines" icon="bar-chart" title="Most dispensed medicines" subtitle="Top 10 by quantity">
    <x-ui.chart type="horizontal-bar"
        :series="[['name' => 'Quantity', 'data' => $top->pluck('total_dispensed')->map(fn ($v) => (int) $v)->all()]]"
        :categories="$top->map(fn ($u) => $u->medicine->name ?? 'Unknown medicine')->all()"
        :height="max(160, 34 * $top->count() + 40)" />
</x-ui.card>
@endif

<x-ui.card flush module="dispensing" title="Dispensing totals" :description="$usage->count().' '.Str::plural('medicine', $usage->count())">
    <x-ui.table responsive="stack" caption="Dispensing totals">
        <x-slot:head>
            <x-ui.th width="3rem" priority="md">#</x-ui.th>
            <x-ui.th>Medicine</x-ui.th>
            <x-ui.th priority="md">Category</x-ui.th>
            <x-ui.th align="end">Times dispensed</x-ui.th>
            <x-ui.th align="end">Quantity</x-ui.th>
            <x-ui.th align="end" priority="sm">Share</x-ui.th>
        </x-slot:head>
        @foreach($usage as $i => $u)
        @php $pct = $totalDispensed > 0 ? round(($u->total_dispensed / $totalDispensed) * 100) : 0; @endphp
        <tr>
            <x-ui.td priority="md" muted>{{ $i + 1 }}</x-ui.td>
            <x-ui.td identity>{{ $u->medicine->name ?? 'Unknown medicine' }}</x-ui.td>
            <x-ui.td label="Category" priority="md" muted>{{ $u->medicine->category->name ?? '-' }}</x-ui.td>
            <x-ui.td label="Times dispensed" numeric>{{ number_format($u->times_dispensed) }}</x-ui.td>
            <x-ui.td label="Quantity" numeric class="fw-semibold">{{ number_format($u->total_dispensed) }}</x-ui.td>
            <x-ui.td label="Share" numeric priority="sm" muted>{{ $pct }}%</x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="capsule" title="No medicines dispensed in this period" description="Try a wider date range." />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

@endsection
