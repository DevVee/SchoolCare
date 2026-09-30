@extends('reports.pdf._layout')

@use('App\Support\DisplayFormat')

@php
    $reportTitle  = 'Medicine usage';
    $reportPeriod = DisplayFormat::date($from).' to '.DisplayFormat::date($to);
@endphp

@section('content')
<table class="summary">
    <tr>
        <td><div class="n">{{ number_format($totalDispensed) }}</div><div class="l">Units dispensed</div></td>
        <td><div class="n">{{ number_format($usage->count()) }}</div><div class="l">Medicines used</div></td>
        <td><div class="n">{{ number_format($usage->sum('times_dispensed')) }}</div><div class="l">Times dispensed</div></td>
    </tr>
</table>

<h2>Dispensing totals</h2>
<table class="data">
    <thead>
        <tr><th>#</th><th>Medicine</th><th>Category</th><th class="num">Times dispensed</th><th class="num">Quantity</th><th class="num">Share</th></tr>
    </thead>
    <tbody>
        @forelse($usage as $i => $u)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $u->medicine->name ?? 'Unknown medicine' }}</td>
            <td>{{ $u->medicine->category->name ?? '-' }}</td>
            <td class="num">{{ number_format($u->times_dispensed) }}</td>
            <td class="num">{{ number_format($u->total_dispensed) }}</td>
            <td class="num">{{ $totalDispensed > 0 ? round(($u->total_dispensed / $totalDispensed) * 100) : 0 }}%</td>
        </tr>
        @empty
        <tr><td colspan="6" class="empty">No medicines dispensed in this period.</td></tr>
        @endforelse
    </tbody>
    @if($usage->isNotEmpty())
    <tfoot>
        <tr><td></td><td>Total</td><td></td><td class="num">{{ number_format($usage->sum('times_dispensed')) }}</td><td class="num">{{ number_format($totalDispensed) }}</td><td class="num">100%</td></tr>
    </tfoot>
    @endif
</table>
@endsection
