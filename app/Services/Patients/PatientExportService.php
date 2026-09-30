<?php

namespace App\Services\Patients;

use App\Models\Patient;
use App\Support\SpreadsheetCell;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CSV / XLSX export of patients. Every text cell goes through
 * SpreadsheetCell::safe() (formula-injection protection).
 */
class PatientExportService
{
    public const FORMATS = ['csv', 'xlsx'];

    /** Download the patients matched by $query. */
    public function download(Builder $query, string $format, string $basename = 'patients'): BinaryFileResponse
    {
        $format = in_array($format, self::FORMATS, true) ? $format : 'csv';
        $path   = $this->tempPath($format);
        $writer = $this->writer($format);

        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_keys($this->header())));

        // chunkById needs its own ordering (id), so drop the list's sort.
        (clone $query)->reorder()->chunkById(500, function ($patients) use ($writer) {
            foreach ($patients as $patient) {
                $writer->addRow(Row::fromValues(SpreadsheetCell::row($this->row($patient))));
            }
        });

        $writer->close();

        return $this->respond($path, $basename.'-'.now()->format('Ymd-His').'.'.$format);
    }

    /** Empty template (header row only) for the import. */
    public function template(string $format): BinaryFileResponse
    {
        $format = in_array($format, self::FORMATS, true) ? $format : 'xlsx';
        $path   = $this->tempPath($format);
        $writer = $this->writer($format);

        $writer->openToFile($path);
        $columns = array_keys(PatientColumns::COLUMNS);
        unset($columns[array_search('patient_number', $columns, true)]);
        $writer->addRow(Row::fromValues(array_values($columns)));
        $writer->close();

        return $this->respond($path, 'patient-import-template.'.$format);
    }

    /** Header keys => labels (export adds the status column). */
    public function header(): array
    {
        return PatientColumns::COLUMNS + ['status' => 'Status'];
    }

    public function row(Patient $patient): array
    {
        $out = [];
        foreach (array_keys(PatientColumns::COLUMNS) as $key) {
            $value = $patient->getAttribute($key);
            $out[] = match (true) {
                $key === 'birthdate' => $patient->birthdate?->format('Y-m-d') ?? '',
                default              => $value === null ? '' : (string) $value,
            };
        }
        $out[] = $patient->trashed() ? 'archived' : ($patient->is_active ? 'active' : 'inactive');

        return $out;
    }

    private function writer(string $format): WriterInterface
    {
        return $format === 'xlsx' ? new XlsxWriter() : new CsvWriter();
    }

    private function tempPath(string $format): string
    {
        $dir = storage_path('app/private/exports');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir.DIRECTORY_SEPARATOR.bin2hex(random_bytes(12)).'.'.$format;
    }

    private function respond(string $path, string $filename): BinaryFileResponse
    {
        $type = str_ends_with($filename, '.xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response()->download($path, $filename, ['Content-Type' => $type])->deleteFileAfterSend();
    }
}
