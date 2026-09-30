{{--
    Letterhead of every printed document (Admin > Settings > Printing).
    One partial for all documents, so they print the same way:

        @include('reports.pdf._letterhead', ['document' => 'reports', 'title' => 'Daily report', 'subtitle' => 'Tuesday, March 10, 2026'])
        @include('reports.pdf._letterhead', ['document' => 'health'])      (health record: it prints its own title block)
        @include('reports.pdf._letterhead', ['document' => 'reports', 'screen' => true, 'printOnly' => true])   (browser print: banner only)

    With a banner uploaded: the banner at its set size and position, then the title (if any).
    Without one (the default): reports print the logo and clinic details from _brand; the
    health record prints a formal letterhead (school logo left, school name, clinic name
    and contact line centred). In screen mode without a banner, `printOnly` pages print the
    school logo and names (the page has its own title); otherwise nothing is added.
    Names are the school and clinic settings, never the product name.
    PDF images are data URIs from the local file (dompdf never fetches URLs).
--}}
@php
    $document = ($document ?? 'reports') === 'health' ? 'health' : 'reports';
    $screen   = (bool) ($screen ?? false);
    $printOnly = (bool) ($printOnly ?? false); // screen mode: hidden on screen, shown when printed
    $title    ??= '';
    $subtitle ??= null;
    $letterheadBanner = \App\Support\PrintBranding::banner($document, embed: ! $screen);
    $letterheadAlt    = \App\Support\PrintBranding::issuerName();
@endphp

@if ($letterheadBanner)
    @if ($screen)
        <div @class(['print-letterhead', 'print-doc-only' => $printOnly]) style="text-align: {{ $letterheadBanner['align'] }};">
            <img src="{{ $letterheadBanner['src'] }}" alt="{{ $letterheadAlt }} letterhead" style="width: {{ $letterheadBanner['width_mm'] }}mm;">
        </div>
    @else
        <div class="letterhead-banner" style="text-align: {{ $letterheadBanner['align'] }}; margin: 0 0 6px; padding: 0;">
            <img src="{{ $letterheadBanner['src'] }}" alt="" style="width: {{ $letterheadBanner['width_mm'] }}mm; height: {{ $letterheadBanner['height_mm'] }}mm;">
        </div>
        @if ($document === 'health')
            @if ($title !== '')<h1>{{ $title }}</h1>@endif
            @if (! empty($subtitle))<div class="muted">{{ $subtitle }}</div>@endif
        @elseif ($title !== '' || ! empty($subtitle))
            <table style="width:100%; border-collapse:collapse; margin:0;">
                <tr>
                    <td style="padding:0; border:none; vertical-align:bottom; background:none;">
                        <div style="font-size:15px; font-weight:bold; color:#000;">{{ $title }}</div>
                    </td>
                    @if (! empty($subtitle))
                        <td style="padding:0; border:none; vertical-align:bottom; text-align:right; background:none;">
                            <div style="font-size:9.5px; color:#222;">{{ $subtitle }}</div>
                        </td>
                    @endif
                </tr>
            </table>
        @endif
    @endif

@elseif (! $screen)
    @if ($document === 'health')
        {{-- Health record: a formal letterhead, school logo on the left and the names centred. --}}
        @php
            $letterheadOrg    = \App\Support\PrintBranding::orgName();
            $letterheadClinic = trim((string) settings('clinic_name', ''));
            if ($letterheadClinic === $letterheadOrg) {
                $letterheadClinic = '';
            }
            if ($letterheadOrg === '' && $letterheadClinic === '') {
                $letterheadClinic = \App\Support\PrintBranding::FALLBACK_NAME;
            }
            $letterheadLine = \App\Support\PrintBranding::contactLine();
            $letterheadLogoPath = \App\Support\PrintBranding::logoPath();
            $letterheadLogo = \App\Support\PrintBranding::dataUri($letterheadLogoPath, 2 * 1024 * 1024);
            $letterheadLogoSize = $letterheadLogo ? @getimagesize($letterheadLogoPath) : false;
            if ($letterheadLogoSize && $letterheadLogoSize[0] > 0 && $letterheadLogoSize[1] > 0) {
                // Fit in an 18 mm square, shape kept (dompdf has no object-fit).
                $letterheadRatio = $letterheadLogoSize[0] / $letterheadLogoSize[1];
                $letterheadLogoW = round($letterheadRatio >= 1 ? 18 : 18 * $letterheadRatio, 2);
                $letterheadLogoH = round($letterheadRatio >= 1 ? 18 / $letterheadRatio : 18, 2);
            } else {
                $letterheadLogo = null;
            }
        @endphp
        <table class="letterhead" style="width:100%; border-collapse:collapse; margin:0;">
            <tr>
                <td style="width:22mm; padding:0; border:none; vertical-align:middle; text-align:left;">
                    @if ($letterheadLogo)<img src="{{ $letterheadLogo }}" alt="" style="width:{{ $letterheadLogoW }}mm; height:{{ $letterheadLogoH }}mm;">@endif
                </td>
                <td style="padding:0; border:none; vertical-align:middle; text-align:center;">
                    @if ($letterheadOrg !== '')
                        <div style="font-size:13pt; font-weight:bold; color:#000; line-height:1.2;">{{ $letterheadOrg }}</div>
                    @endif
                    @if ($letterheadClinic !== '')
                        <div style="font-size:{{ $letterheadOrg !== '' ? '10.5pt' : '13pt' }}; font-weight:bold; color:#000; line-height:1.25;">{{ $letterheadClinic }}</div>
                    @endif
                    @if ($letterheadLine !== '')
                        <div style="font-size:8pt; color:#333; line-height:1.3; margin-top:0.6mm;">{{ $letterheadLine }}</div>
                    @endif
                </td>
                <td style="width:22mm; padding:0; border:none;"></td>
            </tr>
        </table>
        @if ($title !== '')<h1>{{ $title }}</h1>@endif
        @if (! empty($subtitle))<div class="muted">{{ $subtitle }}</div>@endif
    @else
        @include('reports.pdf._brand', ['title' => $title, 'subtitle' => $subtitle, 'report' => true])
    @endif

@elseif ($printOnly)
    {{-- Browser print of a report page without a banner: school logo and names, like the PDF. --}}
    <div class="print-letterhead print-doc-only print-identity">
        @include('reports.pdf._brand', ['screen' => true])
    </div>
@endif
