@extends('layouts.app')

@section('title', 'Inventory snapshot')

@use('App\Support\DisplayFormat')

@section('content')
{{-- Printed only: letterhead banner (Admin > Settings > Printing), as on the PDF --}}
@include('reports.pdf._letterhead', ['document' => 'reports', 'screen' => true, 'printOnly' => true])

<x-ui.page-header title="Inventory snapshot"
    :description="'Current stock as of '.DisplayFormat::date(now()).' '.DisplayFormat::time(now())"
    :breadcrumbs="['Reports' => route('reports.index'), 'Inventory snapshot' => null]">
    <x-slot:actions>
        @include('reports.partials.export', ['type' => 'inventory', 'params' => []])
    </x-slot:actions>
</x-ui.page-header>

@include('reports.partials.stat-cards', ['cards' => [
    ['label' => 'Active medicines', 'value' => $medicines->count(), 'icon' => 'capsule', 'tone' => 'orange', 'module' => 'medicines'],
    ['label' => 'Low stock', 'value' => $lowStock, 'icon' => 'exclamation-triangle', 'tone' => 'warning', 'module' => 'inventory',
     'sub' => $lowStock > 0 ? 'Reorder soon' : 'Nothing to reorder'],
    ['label' => 'Out of stock', 'value' => $outOfStock, 'icon' => 'x-octagon', 'tone' => $outOfStock > 0 ? 'danger' : 'neutral',
     'sub' => $outOfStock > 0 ? 'Cannot be dispensed' : 'All medicines available'],
    ['label' => 'Expiring soon', 'value' => $expiring, 'icon' => 'calendar-x', 'tone' => 'warning',
     'sub' => 'Within '.\App\Models\Medicine::expiryWarningDays().' days'],
    ['label' => 'Expired', 'value' => $expired ?? 0, 'icon' => 'calendar2-x', 'tone' => ($expired ?? 0) > 0 ? 'danger' : 'neutral',
     'sub' => ($expired ?? 0) > 0 ? 'Remove from stock' : 'None expired'],
]])

<x-ui.card flush module="medicines" title="All medicines" :description="$medicines->count().' active '.Str::plural('medicine', $medicines->count())">
    <x-ui.table responsive="stack" caption="All medicines">
        <x-slot:head>
            <x-ui.th>Medicine</x-ui.th>
            <x-ui.th priority="md">Category</x-ui.th>
            <x-ui.th align="end">Quantity</x-ui.th>
            <x-ui.th align="end" priority="lg">Reorder at</x-ui.th>
            <x-ui.th>Expiry</x-ui.th>
            <x-ui.th>Status</x-ui.th>
        </x-slot:head>
        @foreach($medicines as $m)
        @php
            [$stockStatus, $stockLabel] = match (true) {
                $m->quantity == 0 => ['out_of_stock', 'Out of stock'],
                $m->is_low_stock  => ['low_stock', 'Low stock'],
                default           => ['in_stock', 'In stock'],
            };
        @endphp
        <tr>
            <x-ui.td identity>{{ $m->name }}</x-ui.td>
            <x-ui.td label="Category" priority="md" muted>{{ $m->category->name ?? '-' }}</x-ui.td>
            <x-ui.td label="Quantity" numeric>
                <span class="fw-semibold">{{ number_format($m->quantity) }}</span>
                <span class="text-muted fs-xs">{{ $m->unit }}</span>
            </x-ui.td>
            <x-ui.td label="Reorder at" numeric priority="lg" muted>{{ number_format($m->low_stock_threshold) }}</x-ui.td>
            <x-ui.td label="Expiry">
                {{ DisplayFormat::date($m->expiration_date, '-') }}
                @if($m->is_expired)
                    <x-ui.status-badge status="expired" type="stock" size="sm" class="ms-1" />
                @elseif($m->is_expiring_soon)
                    <x-ui.status-badge status="expiring" type="stock" label="Expiring soon" size="sm" class="ms-1" />
                @endif
            </x-ui.td>
            <x-ui.td label="Status"><x-ui.status-badge :status="$stockStatus" type="stock" :label="$stockLabel" size="sm" /></x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="box-seam" title="No active medicines" />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

{{-- Printed only: signatures and footer line, as on the PDF --}}
@include('reports.pdf._signatures', ['document' => 'reports', 'screen' => true])

@endsection
