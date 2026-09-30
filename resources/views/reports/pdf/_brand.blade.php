{{--
    PDF report header: school logo + school / clinic details (Admin > Settings) + report title.
    Usage:
        @include('reports.pdf._brand', ['title' => 'Daily report', 'subtitle' => 'Tuesday, March 10, 2026', 'report' => true])
        @include('reports.pdf._brand', ['title' => 'Health Report Card'])      (compact one-line heading)
    `report` => true renders the report layout (school and clinic on the left, title and period on
    the right). Without it the older compact heading "{clinic}: {title}" is kept for other PDFs.
    The logo is embedded as a data URI from the local file so dompdf never needs remote access.
--}}
@php
    $brandReport   = (bool) ($report ?? false);
    // Reports prefer a separate school logo when one is configured; other PDFs keep the brand logo.
    $brandLogoPath = ($brandReport ? settings()->imagePath('school_logo', false) : null) ?: settings()->imagePath('brand_logo');
    $brandLogoData = null;
    if ($brandLogoPath && is_readable($brandLogoPath) && filesize($brandLogoPath) <= 2 * 1024 * 1024) {
        $brandExt  = strtolower(pathinfo($brandLogoPath, PATHINFO_EXTENSION));
        $brandMime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'][$brandExt] ?? null;
        if ($brandMime) {
            $brandLogoData = 'data:'.$brandMime.';base64,'.base64_encode(file_get_contents($brandLogoPath));
        }
    }
    $brandClinic = trim((string) settings('clinic_name')) ?: (string) settings('app_name');
    $brandOrg    = trim((string) settings('org_name', ''));
    $brandLine   = collect([settings('clinic_address'), settings('clinic_contact'), settings('clinic_email')])
        ->map(fn ($v) => trim(preg_replace('/\s+/', ' ', (string) $v)))
        ->filter()
        ->implode(' · ');
@endphp
<table style="width:100%; border-collapse:collapse; margin:0;">
    <tr>
        @if ($brandLogoData)
            <td style="width:{{ $brandReport ? 54 : 46 }}px; padding:0 10px 0 0; border:none; vertical-align:middle; background:none;">
                <img src="{{ $brandLogoData }}" style="width:{{ $brandReport ? 46 : 40 }}px; height:{{ $brandReport ? 46 : 40 }}px;" alt="">
            </td>
        @endif
        @if ($brandReport)
            <td style="padding:0; border:none; vertical-align:middle; background:none;">
                @if ($brandOrg !== '')
                    <div style="font-size:12px; font-weight:bold; color:#000;">{{ $brandOrg }}</div>
                @endif
                <div style="font-size:{{ $brandOrg !== '' ? 10 : 12 }}px; font-weight:bold; color:#222;">{{ $brandClinic }}</div>
                @if ($brandLine !== '')
                    <div style="font-size:8.5px; color:#444;">{{ $brandLine }}</div>
                @endif
            </td>
            <td style="padding:0; border:none; vertical-align:bottom; text-align:right; background:none;">
                <div style="font-size:15px; font-weight:bold; color:#000;">{{ $title }}</div>
                @if (! empty($subtitle))
                    <div style="font-size:9.5px; color:#222;">{{ $subtitle }}</div>
                @endif
            </td>
        @else
            <td style="padding:0; border:none; vertical-align:middle; background:none;">
                <h1>{{ $brandClinic }}: {{ $title }}</h1>
                @if ($brandLine !== '')
                    <div style="font-size:9px; color:#666;">{{ $brandLine }}</div>
                @endif
            </td>
        @endif
    </tr>
</table>
