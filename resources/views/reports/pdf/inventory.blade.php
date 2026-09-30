@extends('reports.pdf._layout')

@use('App\Support\DisplayFormat')

@php
    $reportTitle  = 'Inventory snapshot';
    $reportPeriod = 'Stock as of '.DisplayFormat::date(now()).' '.DisplayFormat::time(now());
@endphp

@section('content')
<table class="summary">
    <tr>
        <td><div class="n">{{ number_format($medicines->count()) }}</div><div class="l">Active medicines</div></td>
        <td><div class="n">{{ number_format($lowStock) }}</div><div class="l">Low stock</div></td>
        <td><div class="n">{{ number_format($outOfStock) }}</div><div class="l">Out of stock</div></td>
        <td><div class="n">{{ number_format($expiring) }}</div><div class="l">Expiring soon</div></td>
        <td><div class="n">{{ number_format($expired ?? 0) }}</div><div class="l">Expired</div></td>
    </tr>
</table>

<h2>All medicines</h2>
<table class="data">
    <thead>
        <tr><th>Medicine</th><th>Category</th><th class="num">Quantity</th><th>Unit</th><th class="num">Reorder at</th><th>Expiry</th><th>Status</th></tr>
    </thead>
    <tbody>
        @forelse($medicines as $m)
        @php
            $status = $m->quantity == 0 ? 'Out of stock' : ($m->is_low_stock ? 'Low stock' : 'In stock');
            $expiry = $m->is_expired ? ' (expired)' : ($m->is_expiring_soon ? ' (expiring soon)' : '');
            $flag   = $m->quantity == 0 || $m->is_low_stock || $m->is_expired || $m->is_expiring_soon;
        @endphp
        <tr>
            <td>{{ $m->name }}</td>
            <td>{{ $m->category->name ?? '-' }}</td>
            <td class="num">{{ number_format($m->quantity) }}</td>
            <td>{{ $m->unit }}</td>
            <td class="num">{{ number_format($m->low_stock_threshold) }}</td>
            <td class="nowrap">{{ DisplayFormat::date($m->expiration_date, '-') }}{{ $expiry }}</td>
            <td @if($flag) style="font-weight:bold;" @endif>{{ $status }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="empty">No active medicines.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
