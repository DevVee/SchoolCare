<?php

namespace Tests\Feature\Clinical;

/**
 * Reading dompdf output in tests: the page count and the text drawn on the pages.
 * dompdf writes each laid-out line as one text run (UTF-16BE in a Flate stream),
 * so pdfText() returns one line per run.
 */
trait InspectsPdf
{
    protected function pdfPageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page(?![A-Za-z])#', $pdf);
    }

    protected function pdfText(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

        $lines = [];
        foreach ($streams[1] as $raw) {
            $data = @gzuncompress($raw);
            if ($data === false) {
                continue;
            }

            preg_match_all('/\bBT\b(.*?)\bET\b/s', $data, $blocks);
            foreach ($blocks[1] as $block) {
                preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)/s', $block, $strings);
                $line = '';
                foreach ($strings[1] as $string) {
                    // Cpdf escapes ( ) \ and CR byte by byte.
                    $bytes = preg_replace_callback('/\\\\(.)/s', fn ($m) => $m[1] === 'r' ? "\r" : $m[1], $string);
                    if (str_starts_with($bytes, "\xFE\xFF")) {
                        $bytes = substr($bytes, 2);
                    }
                    $line .= mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
                }
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }
}
