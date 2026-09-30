@extends('layouts.app')

@section('title', 'Expiry and disposal')

@php
    $dayChoices = array_unique([7, 14, 30, 60, 90, 180, \App\Models\Medicine::expiryWarningDays()]);
    sort($dayChoices);
    $dayOptions = collect($dayChoices)->mapWithKeys(fn ($d) => [$d => $d.' days'])->all();
    $tabQuery = array_filter(['days' => request()->filled('days') ? $days : null, 'search' => $search !== '' ? $search : null]);
@endphp

@section('content')
<div class="vstack gap-4">
    <x-ui.page-header :title="'Expiry and disposal'"
        :description="'Batches that have expired or expire within '.$days.' days. Expired stock is never given out.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Medicines' => route('medicines.index'), 'Expiry and disposal' => null]">
        <x-slot:actions>
            @can('view-inventory')
                <x-ui.button variant="secondary" icon="clock-history" :href="route('disposals.index')">Disposal history</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('medicines.partials.module-tabs', ['active' => 'expiring'])

    <x-ui.tabs label="Show batches" variant="underline" :active="$view" :items="[
        'all' => ['label' => 'Expired and expiring', 'count' => $counts['expired'] + $counts['expiring'], 'href' => route('medicines.expiring', $tabQuery)],
        'expired' => ['label' => 'Expired', 'count' => $counts['expired'], 'href' => route('medicines.expiring', $tabQuery + ['view' => 'expired'])],
        'expiring' => ['label' => 'Expiring within '.$days.' days', 'count' => $counts['expiring'], 'href' => route('medicines.expiring', $tabQuery + ['view' => 'expiring'])],
    ]" />

    <x-ui.filters :action="route('medicines.expiring')" search-placeholder="Name, generic name or barcode"
        :labels="['days' => 'Expiring within']" :options="['days' => $dayOptions]" :keep="['view']">
        <x-slot:inline>
            <x-ui.select name="days" size="sm" aria-label="Expiring within" :options="$dayOptions" :selected="$days" />
        </x-slot:inline>
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table responsive="stack" :paginator="$batches" noun="batches" caption="Expired and expiring batches">
            <x-slot:head>
                <x-ui.th>Medicine</x-ui.th>
                <x-ui.th priority="md">Batch no.</x-ui.th>
                <x-ui.th>Expiry</x-ui.th>
                <x-ui.th align="end">Quantity</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($batches as $b)
                <tr>
                    <x-ui.td identity>
                        <a href="{{ route('medicines.show', $b->medicine_id) }}#batches" class="cell-title d-block">{{ $b->medicine?->name }}</a>
                        @if ($b->medicine?->category)
                            <span class="cell-sub d-block">{{ $b->medicine->category->name }}</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td priority="md" label="Batch no." class="font-monospace">{{ $b->batch_number ?: '-' }}</x-ui.td>
                    <x-ui.td label="Expiry">@include('medicines.partials.expiry', ['date' => $b->expiry_date, 'daysLeft' => $b->days_until_expiry, 'soon' => true, 'ago' => true])</x-ui.td>
                    <x-ui.td numeric label="Quantity">@include('medicines.partials.qty', ['qty' => $b->quantity, 'unit' => $b->medicine?->unit])</x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="($b->medicine?->name ?? 'batch').', '.($b->batch_number ?: 'no batch no.')">
                            <x-ui.action-menu.item :href="route('medicines.show', $b->medicine_id).'#batches'" icon="eye">View medicine</x-ui.action-menu.item>
                            @can('dispose-medicines')
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item icon="trash3" danger class="btn-dispose"
                                    data-action="{{ route('disposals.store', $b) }}"
                                    data-label="{{ $b->medicine?->name }}, {{ $b->label }}"
                                    data-qty="{{ number_format($b->quantity) }} {{ \Illuminate\Support\Str::plural($b->medicine?->unit ?: 'unit', $b->quantity) }}"
                                    data-expired="{{ $b->is_expired ? '1' : '0' }}">Dispose...</x-ui.action-menu.item>
                            @endcan
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($search !== '')
                    <x-ui.empty-state icon="search" title="No batches match this search" description="Try a different name or clear the search." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('medicines.expiring', ['view' => $view])">Clear search</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="shield-check" tone="success" title="Nothing to act on"
                        :description="'No batch with stock has expired or expires within '.$days.' days.'" compact />
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>

@include('medicines.partials.dispose-modal')
@endsection
