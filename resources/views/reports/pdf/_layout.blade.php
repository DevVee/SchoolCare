{{--
    Shared PDF report layout (dompdf). Black-and-white friendly: black text, grey rules,
    light grey table headers, no colour fills. Child views set $reportTitle and $reportPeriod
    in a PHP block before the content section.
    Helpers: table.data (th.num / td.num right-aligned), table.summary (number tiles),
    h2 section headings, .empty for "no records" rows.
--}}
@php
    $reportTitle  ??= 'Report';
    $reportPeriod ??= null;
    $generatedAt  = \App\Support\DisplayFormat::date(now()).' '.\App\Support\DisplayFormat::time(now());
    $generatedBy  = auth()->user()?->name;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $reportTitle }}</title>
<style>
    @page { margin: 16mm 14mm 18mm 14mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; margin: 0; line-height: 1.35; }
    .report-head { border-bottom: 1.5px solid #000; padding-bottom: 6px; margin-bottom: 12px; }
    h2 { font-size: 11px; font-weight: bold; color: #000; margin: 14px 0 5px; page-break-after: avoid; }
    p  { margin: 0 0 8px; }
    .muted { color: #555; }

    table.data { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
    table.data th {
        text-align: left; font-size: 8.5px; font-weight: bold; color: #000;
        background: #EDEDED; border-top: 1px solid #000; border-bottom: 1px solid #000;
        padding: 4px 6px;
    }
    table.data td { padding: 3.5px 6px; border-bottom: 0.5px solid #BDBDBD; vertical-align: top; }
    table.data tr { page-break-inside: avoid; }
    table.data tfoot td { font-weight: bold; border-top: 1px solid #000; border-bottom: none; }
    table.data .num { text-align: right; white-space: nowrap; }
    table.data .nowrap { white-space: nowrap; }
    table.data td.empty { text-align: center; color: #555; font-style: italic; padding: 8px 6px; }

    table.summary { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0 0 12px; }
    table.summary td { border: 0.75px solid #999; padding: 6px 8px; vertical-align: top; }
    table.summary .n { font-size: 14px; font-weight: bold; color: #000; }
    table.summary .l { font-size: 8px; color: #444; }

    table.cols { width: 100%; border-collapse: collapse; }
    table.cols > tbody > tr > td.col { vertical-align: top; padding: 0; }
    table.cols > tbody > tr > td.gap { width: 4%; padding: 0; }

    .footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; font-size: 7.5px; color: #555; border-top: 0.5px solid #999; padding-top: 3px; }
    .footer table { width: 100%; border-collapse: collapse; }
    .footer td { padding: 0; border: none; }
    .pagenum:before { content: counter(page); }
</style>
</head>
<body>
<div class="footer">
    <table>
        <tr>
            <td>{{ $reportTitle }}@if ($reportPeriod), {{ $reportPeriod }}@endif</td>
            <td style="text-align:right;">Generated {{ $generatedAt }}@if ($generatedBy) by {{ $generatedBy }}@endif. Page <span class="pagenum"></span></td>
        </tr>
    </table>
</div>

<div class="report-head">
    @include('reports.pdf._brand', ['title' => $reportTitle, 'subtitle' => $reportPeriod, 'report' => true])
</div>

@yield('content')
</body>
</html>
