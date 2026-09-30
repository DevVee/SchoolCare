{{--
    Signature block of every printed document (Admin > Settings > Printing > Signatures).
    One partial for all documents, so they sign off the same way:

        @include('reports.pdf._signatures', ['document' => 'reports'])                      (PDF)
        @include('reports.pdf._signatures', ['document' => 'health', 'screen' => true])     (browser print)

    Prints the signed-in user ("Prepared by", when turned on) and signatories 1 to 3 that
    have a name and are turned on for this document: caption, signature image (or blank
    space to sign by hand), a line, the name, position and license number. At least three
    columns wide so a single signatory does not stretch across the page.
    Nothing is printed when no signatory applies (the default).

    Screen mode (browser print): image URLs, hidden on screen, and it also prints the
    footer line, because a browser page has no fixed PDF footer.
--}}
@php
    $document = ($document ?? 'reports') === 'health' ? 'health' : 'reports';
    $screen   = (bool) ($screen ?? false);
    $signers  = \App\Support\PrintBranding::signatories($document, auth()->user(), embed: ! $screen);
    $signCols = max(3, count($signers));
    $signPct  = round(100 / $signCols, 3);
    $signRow  = max(\App\Support\PrintBranding::BLANK_SIGNATURE_MM, ...array_map(fn ($s) => $s['image_height_mm'], $signers ?: [['image_height_mm' => 0]]));
    $hasPosition = collect($signers)->contains(fn ($s) => $s['position'] !== '');
    $hasLicense  = collect($signers)->contains(fn ($s) => $s['license'] !== '');
    $signFooter  = $screen ? \App\Support\PrintBranding::footerText($document) : '';
    $cell = 'border:none; background:none; padding:0; width:'.$signPct.'%;';
@endphp

@if ($signers || $signFooter !== '')
    @if ($screen)<div class="print-signoff print-doc-only">@endif

    @if ($signers)
        <div class="signatures" style="page-break-inside: avoid; margin-top: 10mm;">
            <table style="width:100%; border-collapse:collapse; table-layout:fixed; margin:0;">
                <tr>
                    @foreach ($signers as $s)
                        <td style="{{ $cell }} vertical-align:top;">
                            <div style="margin:0 4mm; font-size:8.5px; color:#444;">{{ $s['caption'] }}</div>
                        </td>
                    @endforeach
                    @for ($i = count($signers); $i < $signCols; $i++)<td style="{{ $cell }}"></td>@endfor
                </tr>
                <tr>
                    @foreach ($signers as $s)
                        <td style="{{ $cell }} height:{{ $signRow }}mm; vertical-align:bottom; text-align:center;">
                            @if ($s['image'])
                                <img src="{{ $s['image'] }}" alt="{{ $screen ? 'Signature of '.$s['name'] : '' }}"
                                     style="width:{{ $s['image_width_mm'] }}mm; height:{{ $s['image_height_mm'] }}mm;">
                            @endif
                        </td>
                    @endforeach
                    @for ($i = count($signers); $i < $signCols; $i++)<td style="{{ $cell }}"></td>@endfor
                </tr>
                <tr>
                    @foreach ($signers as $s)
                        <td style="{{ $cell }} vertical-align:top; text-align:center;">
                            <div style="margin:0 4mm; border-top:0.75px solid #000; padding-top:2px; font-size:9.5px; font-weight:bold; color:#000;">{{ $s['name'] }}</div>
                        </td>
                    @endforeach
                    @for ($i = count($signers); $i < $signCols; $i++)<td style="{{ $cell }}"></td>@endfor
                </tr>
                @if ($hasPosition)
                    <tr>
                        @foreach ($signers as $s)
                            <td style="{{ $cell }} vertical-align:top; text-align:center;">
                                <div style="margin:0 4mm; font-size:8.5px; color:#222;">{{ $s['position'] }}</div>
                            </td>
                        @endforeach
                        @for ($i = count($signers); $i < $signCols; $i++)<td style="{{ $cell }}"></td>@endfor
                    </tr>
                @endif
                @if ($hasLicense)
                    <tr>
                        @foreach ($signers as $s)
                            <td style="{{ $cell }} vertical-align:top; text-align:center;">
                                @if ($s['license'] !== '')
                                    <div style="margin:0 4mm; font-size:8px; color:#444;">License No. {{ $s['license'] }}</div>
                                @endif
                            </td>
                        @endforeach
                        @for ($i = count($signers); $i < $signCols; $i++)<td style="{{ $cell }}"></td>@endfor
                    </tr>
                @endif
            </table>
        </div>
    @endif

    @if ($screen)
        @if ($signFooter !== '')<p class="print-signoff-footer">{{ $signFooter }}</p>@endif
        </div>
    @endif
@endif
