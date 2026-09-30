{{--
    Letterhead without a banner: school logo + school / clinic details (Admin > Settings) + title.
    Usage (through reports/pdf/_letterhead):
        @include('reports.pdf._brand', ['title' => 'Daily report', 'subtitle' => 'Tuesday, March 10, 2026', 'report' => true])
        @include('reports.pdf._brand', ['title' => 'Health Report Card'])      (compact one-line heading)
        @include('reports.pdf._brand', ['screen' => true])                     (browser print: names only)
    `report` => true renders the report layout (school and clinic on the left, title and period on
    the right). Without it the compact heading "{clinic}: {title}" is used.
    Names come from the school and clinic settings, never the product name; with neither set it
    reads "School Clinic". The logo is the uploaded school logo only (no product mark), embedded
    as a data URI from the local file so dompdf never needs remote access.
--}}
@php
    $brandReport = (bool) ($report ?? false);
    $brandScreen = (bool) ($screen ?? false);
    $title ??= '';
    $brandLogoData = $brandScreen
        ? \App\Support\PrintBranding::logoUrl()
        : \App\Support\PrintBranding::dataUri(\App\Support\PrintBranding::logoPath(), 2 * 1024 * 1024);
    $brandOrg    = \App\Support\PrintBranding::orgName();
    $brandClinic = trim((string) settings('clinic_name', ''));
    if ($brandClinic === $brandOrg) {
        $brandClinic = '';
    }
    if ($brandOrg === '' && $brandClinic === '') {
        $brandClinic = \App\Support\PrintBranding::FALLBACK_NAME;
    }
    $brandLine = \App\Support\PrintBranding::contactLine();
@endphp
<table style="width:100%; border-collapse:collapse; margin:0;">
    <tr>
        @if ($brandLogoData)
            <td style="width:{{ $brandReport || $brandScreen ? 54 : 46 }}px; padding:0 10px 0 0; border:none; vertical-align:middle; background:none;">
                <img src="{{ $brandLogoData }}" style="width:{{ $brandReport || $brandScreen ? 46 : 40 }}px; height:{{ $brandReport || $brandScreen ? 46 : 40 }}px; object-fit:contain;" alt="">
            </td>
        @endif
        @if ($brandReport || $brandScreen)
            <td style="padding:0; border:none; vertical-align:middle; background:none;">
                @if ($brandOrg !== '')
                    <div style="font-size:12px; font-weight:bold; color:#000;">{{ $brandOrg }}</div>
                @endif
                @if ($brandClinic !== '')
                    <div style="font-size:{{ $brandOrg !== '' ? 10 : 12 }}px; font-weight:bold; color:#222;">{{ $brandClinic }}</div>
                @endif
                @if ($brandLine !== '')
                    <div style="font-size:8.5px; color:#444;">{{ $brandLine }}</div>
                @endif
            </td>
            @if ($brandReport)
                <td style="padding:0; border:none; vertical-align:bottom; text-align:right; background:none;">
                    <div style="font-size:15px; font-weight:bold; color:#000;">{{ $title }}</div>
                    @if (! empty($subtitle))
                        <div style="font-size:9.5px; color:#222;">{{ $subtitle }}</div>
                    @endif
                </td>
            @endif
        @else
            <td style="padding:0; border:none; vertical-align:middle; background:none;">
                <h1>{{ $brandClinic !== '' ? $brandClinic : $brandOrg }}: {{ $title }}</h1>
                @if ($brandLine !== '')
                    <div style="font-size:9px; color:#666;">{{ $brandLine }}</div>
                @endif
            </td>
        @endif
    </tr>
</table>
