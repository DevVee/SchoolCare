@extends('layouts.app')

@section('title', 'Disposal history')

@php
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(2000, $m, 1)->format('F')])->all();
    $medicineOptions = $medicines->pluck('name', 'id')->all();
    $yearOptions = collect($years)->mapWithKeys(fn ($y) => [$y => (string) $y])->all();
    $hasFilters = collect($filters)->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
@endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Disposal history'" description="Batches removed from stock because they expired or could not be used."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Inventory' => route('inventory.index'), 'Disposal history' => null]">
        <x-slot:actions>
            @can('view-medicines')
                <x-ui.button variant="secondary" icon="calendar-x" :href="route('medicines.expiring')">Expiry and disposal</x-ui.button>
            @endcan
            @can('export-reports')
                <x-ui.button variant="secondary" icon="download" :href="route('disposals.export', request()->query())">Export CSV</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('inventory.partials.module-tabs', ['active' => 'disposals'])

    <div class="row g-3">
        <div class="col-6 col-lg-4"><x-ui.stat-card class="h-100" label="Disposals" :value="number_format($totals['count'])" icon="archive" tone="warning" sub="Batches removed" /></div>
        <div class="col-6 col-lg-4"><x-ui.stat-card class="h-100" label="Units disposed" :value="number_format($totals['quantity'])" icon="box-seam" tone="warning" sub="Across all medicines" /></div>
        <div class="col-12 col-lg-4"><x-ui.stat-card class="h-100" label="Total cost" :value="number_format($totals['cost'], 2)" icon="cash-stack" tone="warning" sub="Batches without a cost are not counted" /></div>
    </div>

    <x-ui.filters :action="route('disposals.index')" search-placeholder="Medicine, batch or reason"
        :labels="['date_from' => 'From', 'date_to' => 'To', 'medicine_id' => 'Medicine', 'year' => 'Year', 'month' => 'Month']"
        :options="['medicine_id' => $medicineOptions, 'month' => $months]">
        <x-slot:inline>
            <x-ui.input name="date_from" type="date" size="sm" aria-label="From date" :value="$filters['date_from']" />
            <x-ui.input name="date_to" type="date" size="sm" aria-label="To date" :value="$filters['date_to']" />
        </x-slot:inline>
        <x-ui.select name="medicine_id" label="Medicine" size="sm" :options="$medicineOptions" placeholder="All medicines" :selected="$filters['medicine_id']" />
        <x-ui.select name="year" label="Year" size="sm" :options="$yearOptions" placeholder="All years" :selected="$filters['year']" />
        <x-ui.select name="month" label="Month" size="sm" :options="$months" placeholder="All months" :selected="$filters['month']" />
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table responsive="stack" :paginator="$disposals" noun="disposals" caption="Disposal history">
            <x-slot:head>
                <x-ui.th>Date</x-ui.th>
                <x-ui.th>Medicine</x-ui.th>
                <x-ui.th priority="md">Batch no.</x-ui.th>
                <x-ui.th priority="lg">Expiry</x-ui.th>
                <x-ui.th align="end">Quantity</x-ui.th>
                <x-ui.th align="end" priority="md">Cost</x-ui.th>
                <x-ui.th priority="lg">Reason</x-ui.th>
                <x-ui.th priority="xl">Disposed by</x-ui.th>
            </x-slot:head>

            @foreach ($disposals as $d)
                <tr>
                    <x-ui.td label="Date">{{ $d->disposed_at->format('M j, Y, g:i A') }}</x-ui.td>
                    <x-ui.td identity>
                        @if ($d->medicine)
                            <a href="{{ route('medicines.show', $d->medicine_id) }}" class="cell-title">{{ $d->medicine->name }}</a>
                        @else
                            <span class="text-muted">Removed medicine</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td priority="md" label="Batch no." class="font-monospace">{{ $d->batch_number ?: '-' }}</x-ui.td>
                    <x-ui.td priority="lg" label="Expiry">@include('medicines.partials.expiry', ['date' => $d->expiry_date, 'badge' => false])</x-ui.td>
                    <x-ui.td numeric label="Quantity">@include('medicines.partials.qty', ['qty' => $d->quantity, 'unit' => $d->medicine?->unit])</x-ui.td>
                    <x-ui.td numeric priority="md" label="Cost">{{ $d->total_cost !== null ? number_format((float) $d->total_cost, 2) : '-' }}</x-ui.td>
                    <x-ui.td priority="lg" label="Reason" truncate>{{ $d->reason }}</x-ui.td>
                    <x-ui.td priority="xl" label="Disposed by" muted>{{ $d->disposedBy?->name ?? 'Deleted user' }}</x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($hasFilters)
                    <x-ui.empty-state icon="search" title="No disposals match these filters" description="Try a different date range or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('disposals.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="archive" title="No disposals yet" description="Batches disposed of from the expiry page are listed here." compact />
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
