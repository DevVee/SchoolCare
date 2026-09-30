<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\AuditLogService;
use App\Services\Patients\HealthReportService;
use App\Support\PrintBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Dompdf\Frame;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Patient health report (SSCMS patients/patient_health_report.php): the on-screen
 * report page, and the printable health record, a plain official A4 document.
 *
 * The record is one page for a typical patient and never more than two. It is laid
 * out, measured (which page each visit row landed on) and, when the history is too
 * long, laid out again with fewer rows; the older visits are counted instead
 * ("and 25 earlier visits on file").
 */
class PatientHealthReportController extends Controller
{
    /** Most pages a printed health record takes. */
    public const MAX_PAGES = 2;

    /** Most visit rows ever listed (the newest); older ones are counted. */
    public const MAX_ROWS = 60;

    /** Rows carried over with the certification when it would stand alone on the last page. */
    private const KEEP_WITH_CLOSING = 2;

    public function __construct(private readonly HealthReportService $reports) {}

    public function show(Patient $patient)
    {
        $this->authorize('view-patients');

        return view('patients.health-report', $this->reports->build($patient));
    }

    /** The health record PDF: downloaded, or shown in the browser to print (?inline=1). */
    public function pdf(Request $request, Patient $patient)
    {
        $this->authorize('view-patients');

        $inline = $request->boolean('inline');
        $pdf = $this->record($this->reports->build($patient));

        AuditLogService::log(
            action: 'exported',
            module: 'patients',
            description: ($inline ? 'Opened the health record PDF to print' : 'Downloaded the health record PDF')
                ." of {$patient->full_name} ({$patient->patient_number})",
        );

        $name = 'health-record-'.Str::slug($patient->last_name.'-'.$patient->first_name).'-'.now()->format('Ymd').'.pdf';

        return $inline ? $pdf->stream($name) : $pdf->download($name);
    }

    // ─── Layout ──────────────────────────────────────────────────────────────

    /** Lays the record out within MAX_PAGES and numbers its pages. */
    private function record(array $data): PdfDocument
    {
        $limit = self::MAX_ROWS;
        $keep = 0;
        $fits = null; // last layout within MAX_PAGES: [pdf, layout]

        for ($attempt = 0; $attempt < 6; $attempt++) {
            [$pdf, $layout] = $this->layout($data, $limit, $keep);
            $rows = count($layout['rows']);

            if ($layout['pages'] <= self::MAX_PAGES) {
                $fits = [$pdf, $layout];

                // The certification alone on the last page: carry the last rows over with it.
                $last = $layout['pages'];
                $rowsOnLast = count(array_filter($layout['rows'], fn ($r) => $r['page'] === $last));
                if ($last > 1 && $rowsOnLast === 0 && $keep === 0 && $rows > self::KEEP_WITH_CLOSING) {
                    $keep = self::KEEP_WITH_CLOSING;

                    continue;
                }

                break;
            }

            if ($fits || $rows === 0) {
                break; // carrying rows over made it longer, or nothing is left to shorten
            }

            $limit = min($rows - 1, $this->rowsThatFit($layout));
        }

        [$pdf, $layout] = $fits ?? [$pdf, $layout];
        $this->numberPages($pdf, $layout['footer']);

        return $pdf;
    }

    /**
     * Renders the record with the newest $limit history rows and reports where things
     * landed: page count, each row's page and position, the closing block's height.
     *
     * @return array{0: PdfDocument, 1: array{pages:int, rows:array<int, array{page:int, top:float, bottom:float}>, closing:float, earlier:int, bottom:float, footer:?float}}
     */
    private function layout(array $data, int $limit, int $keep): array
    {
        $layout = ['pages' => 0, 'rows' => [], 'closing' => 0.0, 'earlier' => 0, 'bottom' => 0.0, 'footer' => null];

        $pdf = Pdf::loadView('patients.pdf.health-report', $data + [
            'historyLimit'    => $limit,
            'keepWithClosing' => $keep,
        ])->setPaper('a4', 'portrait');

        $pdf->getDomPDF()->setCallbacks([
            [
                'event' => 'begin_page_render',
                'f'     => function (Frame $frame) use (&$layout) {
                    $box = $frame->get_containing_block();
                    $layout['bottom'] = (float) $box['y'] + (float) $box['h'];
                },
            ],
            [
                'event' => 'begin_frame',
                'f'     => function (Frame $frame, Canvas $canvas) use (&$layout) {
                    $node = $frame->get_node();
                    if (! $node instanceof \DOMElement) {
                        return;
                    }
                    if ($node->hasAttribute('data-row')) {
                        $top = (float) $frame->get_position('y');
                        $layout['rows'][(int) $node->getAttribute('data-row')] = [
                            'page'   => $canvas->get_page_number(),
                            'top'    => $top,
                            'bottom' => $top + (float) $frame->get_margin_height(),
                        ];
                    } elseif ($node->getAttribute('data-block') === 'closing') {
                        $layout['closing'] = max($layout['closing'], (float) $frame->get_margin_height());
                        $layout['earlier'] = (int) $node->getAttribute('data-earlier');
                    } elseif ($node->getAttribute('data-block') === 'footer') {
                        $layout['footer'] ??= (float) $frame->get_content_box()['y'];
                    }
                },
            ],
        ]);

        $pdf->render();
        $layout['pages'] = $pdf->getDomPDF()->getCanvas()->get_page_count();
        ksort($layout['rows']);

        return [$pdf, $layout];
    }

    /**
     * How many of the newest rows fit on the first MAX_PAGES pages with room left
     * below them for the closing block (the "earlier visits" line, the certification
     * and the signatures).
     */
    private function rowsThatFit(array $layout): int
    {
        $fit = array_filter($layout['rows'], fn ($r) => $r['page'] <= self::MAX_PAGES);
        // Room for the closing block, plus the "earlier visits" line (about 24 pt) when it is new.
        $need = $layout['closing'] + ($layout['earlier'] > 0 ? 2.0 : 26.0);

        while ($fit !== []) {
            $last = end($fit);
            // Rows ending before the last page leave it free for the closing block.
            if ($last['page'] < self::MAX_PAGES || $layout['bottom'] - $last['bottom'] >= $need) {
                break;
            }
            array_pop($fit);
        }

        return count($fit);
    }

    /**
     * "Page 1 of 2" at the right of the footer's first line (its top measured in
     * layout()), when turned on in Settings > Printing.
     */
    private function numberPages(PdfDocument $pdf, ?float $footerTop): void
    {
        if ($footerTop === null || ! PrintBranding::pageNumbers(PrintBranding::HEALTH)) {
            return;
        }

        $pdf->getDomPDF()->getCanvas()->page_script(function (int $page, int $pages, Canvas $canvas, FontMetrics $metrics) use ($footerTop) {
            $font = $metrics->getFont('DejaVu Serif');
            $size = 7.5;
            $text = "Page {$page} of {$pages}";
            $x = $canvas->get_width() - 18 * 72 / 25.4 - $metrics->getTextWidth($text, $font, $size); // right margin 18 mm
            $canvas->text($x, $footerTop + 0.9, $text, $font, $size, [0.2, 0.2, 0.2]);
        });
    }
}
