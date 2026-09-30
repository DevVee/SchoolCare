<?php

namespace App\Services\Patients;

use App\Http\Requests\Patient\PatientRules;
use App\Models\Patient;
use App\Services\AuditLogService;
use App\Services\PatientService;
use App\Support\AcademicLists;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Patient import from .xlsx / .csv (SSCMS "Import" button, done server-side).
 *
 * Flow: store() keeps the uploaded file privately under a random token,
 * analyze() parses + validates every row (same rules as the staff form) and
 * flags duplicates, import() re-analyzes and inserts only the valid rows in
 * one transaction, then deletes the file.
 */
class PatientImportService
{
    public const MAX_ROWS = 5000;
    public const MAX_KB   = 5120;
    private const DIR     = 'app/private/imports';

    /** SSCMS / common spellings => category value (only used when that value exists). */
    private const CATEGORY_ALIASES = [
        'pre school' => 'kinder', 'preschool' => 'kinder', 'pre-school' => 'kinder', 'kindergarten' => 'kinder',
        'jhs' => 'junior_high', 'junior high' => 'junior_high', 'junior high school' => 'junior_high',
        'shs' => 'senior_high', 'senior high' => 'senior_high', 'senior high school' => 'senior_high',
        'faculty and staff' => 'teacher', 'faculty' => 'teacher', 'teachers' => 'teacher',
        'staff' => 'employee', 'employees' => 'employee', 'non-teaching' => 'employee',
        'alumnus' => 'alumni', 'alumna' => 'alumni',
        'grade school' => 'elementary', 'elem' => 'elementary',
    ];

    private const PHONE_FIELDS = ['contact_number', 'other_contact', 'guardian_contact', 'emergency_contact_number', 'pediatrician_contact'];

    public function __construct(private readonly PatientService $patients) {}

    // ─── File handling ───────────────────────────────────────────────────────

    /** Keep the upload privately; returns the token that identifies it. */
    public function store(UploadedFile $file): string
    {
        $ext   = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $token = Str::random(40);
        $dir   = storage_path(self::DIR);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file->move($dir, "{$token}.{$ext}");

        return $token;
    }

    public function path(string $token): ?string
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        foreach (['xlsx', 'csv'] as $ext) {
            $path = storage_path(self::DIR."/{$token}.{$ext}");
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function discard(string $token): void
    {
        if ($path = $this->path($token)) {
            @unlink($path);
        }
    }

    /** Content sniff: an .xlsx must be a zip, a .csv must be text. */
    public static function looksValid(UploadedFile $file): bool
    {
        $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 4096);
        $ext  = strtolower($file->getClientOriginalExtension());

        return $ext === 'xlsx'
            ? str_starts_with($head, "PK\x03\x04")
            : ! str_contains($head, "\0");
    }

    // ─── Parsing ─────────────────────────────────────────────────────────────

    /**
     * @return array{headers: array, missing: array, unknown: array, rows: array, too_many: bool}
     */
    public function read(string $path): array
    {
        $isXlsx = str_ends_with(strtolower($path), '.xlsx');

        if ($isXlsx) {
            $reader = new XlsxReader();
        } else {
            $options = new CsvOptions();
            $sample  = (string) file_get_contents($path, false, null, 0, 65536);
            $firstLine = strtok($sample, "\r\n") ?: '';
            $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            if (! mb_check_encoding($sample, 'UTF-8')) {
                $options->ENCODING = 'Windows-1252';
            }
            $reader = new CsvReader($options);
        }

        $reader->open($path);

        $headers = [];
        $rows    = [];
        $tooMany = false;
        $line    = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = $row->toArray();

                    if ($headers === []) {
                        $headers = array_map(function ($h) {
                            $h = (string) $h;
                            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // UTF-8 BOM

                            return PatientColumns::normalizeHeader($h);
                        }, $cells);
                        continue;
                    }

                    if (count(array_filter($cells, fn ($c) => trim($this->scalar($c)) !== '')) === 0) {
                        continue; // blank row
                    }

                    if (count($rows) >= self::MAX_ROWS) {
                        $tooMany = true;
                        break 2;
                    }

                    $assoc = [];
                    foreach ($headers as $i => $key) {
                        if ($key === '') {
                            continue;
                        }
                        $assoc[$key] = $cells[$i] ?? null;
                    }
                    $rows[] = ['line' => $line, 'raw' => $assoc];
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        $known   = array_merge(array_keys(PatientColumns::COLUMNS), ['program_section', 'status']);
        $present = array_values(array_filter($headers, fn ($h) => $h !== ''));

        return [
            'headers'  => $present,
            'missing'  => array_values(array_diff(PatientColumns::REQUIRED, $present)),
            'unknown'  => array_values(array_diff($present, $known)),
            'rows'     => $rows,
            'too_many' => $tooMany,
        ];
    }

    /**
     * Parse + validate every row.
     *
     * @return array{missing: array, unknown: array, too_many: bool, rows: array, counts: array}
     */
    public function analyze(string $path): array
    {
        $sheet = $this->read($path);

        $result = [
            'missing'  => $sheet['missing'],
            'unknown'  => $sheet['unknown'],
            'too_many' => $sheet['too_many'],
            'rows'     => [],
            'counts'   => ['valid' => 0, 'error' => 0, 'duplicate' => 0, 'total' => count($sheet['rows'])],
        ];

        if ($sheet['missing'] !== []) {
            return $result;
        }

        $existing = $this->existingIndex();
        $seenIds  = [];
        $seenKeys = [];

        foreach ($sheet['rows'] as $row) {
            $data   = $this->normalize($row['raw']);
            $errors = $this->validate($data);
            $dup    = null;

            $sid = mb_strtolower((string) ($data['student_id'] ?? ''));
            $key = $this->nameKey($data);

            if ($sid !== '' && isset($seenIds[$sid])) {
                $dup = "Same student ID as line {$seenIds[$sid]} in this file.";
            } elseif ($key && isset($seenKeys[$key])) {
                $dup = "Same name and birthdate as line {$seenKeys[$key]} in this file.";
            } elseif ($sid !== '' && isset($existing['ids'][$sid])) {
                $dup = "Student ID already belongs to {$existing['ids'][$sid]}.";
                unset($errors['student_id']); // same fact, reported once
            } elseif ($key && isset($existing['keys'][$key])) {
                $dup = "Already registered as {$existing['keys'][$key]}.";
            }

            if ($sid !== '') {
                $seenIds[$sid] ??= $row['line'];
            }
            if ($key) {
                $seenKeys[$key] ??= $row['line'];
            }

            $status = $errors ? 'error' : ($dup ? 'duplicate' : 'valid');
            $result['counts'][$status]++;

            $result['rows'][] = [
                'line'      => $row['line'],
                'data'      => $data,
                'errors'    => $errors,
                'duplicate' => $dup,
                'status'    => $status,
            ];
        }

        return $result;
    }

    // ─── Import ──────────────────────────────────────────────────────────────

    /**
     * Insert the valid rows (errors and duplicates are skipped) in one
     * transaction. Returns ['imported' => n, 'skipped' => n, 'numbers' => [...]].
     */
    public function import(string $path, string $filename = 'upload'): array
    {
        $analysis = $this->analyze($path);
        if ($analysis['missing'] !== []) {
            return ['imported' => 0, 'skipped' => $analysis['counts']['total'], 'first' => null, 'last' => null];
        }

        $valid   = array_values(array_filter($analysis['rows'], fn ($r) => $r['status'] === 'valid'));
        $userId  = auth()->id();
        $numbers = [];

        DB::transaction(function () use ($valid, $userId, &$numbers) {
            foreach (array_chunk($valid, 200) as $chunk) {
                Patient::withoutEvents(function () use ($chunk, $userId, &$numbers) {
                    foreach ($chunk as $row) {
                        $data = array_intersect_key($row['data'], array_flip(PatientRules::fields()));
                        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
                        $data['patient_number'] = $this->patients->generatePatientNumber();
                        $data['created_by']     = $userId;
                        $data['is_active']      = true;

                        $numbers[] = Patient::create($data)->patient_number;
                    }
                });
            }
        });

        $skipped = $analysis['counts']['total'] - count($numbers);

        AuditLogService::log(
            action: 'imported',
            module: 'patients',
            description: 'Imported '.count($numbers).' patient(s) from '.Str::limit($filename, 80)
                .($skipped ? ", skipped {$skipped} row(s) with errors or duplicates" : '')
                .($numbers ? ' ('.$numbers[0].' to '.end($numbers).')' : ''),
            newValues: ['imported' => count($numbers), 'skipped' => $skipped, 'patient_numbers' => $numbers],
        );

        return [
            'imported' => count($numbers),
            'skipped'  => $skipped,
            'first'    => $numbers[0] ?? null,
            'last'     => $numbers ? end($numbers) : null,
        ];
    }

    // ─── Row helpers ─────────────────────────────────────────────────────────

    /** Map raw cells to patient attributes (canonical choice values). */
    public function normalize(array $raw): array
    {
        $data = [];
        foreach (array_keys(PatientColumns::COLUMNS) as $key) {
            if ($key === 'patient_number') {
                continue;
            }
            if (array_key_exists($key, $raw)) {
                $data[$key] = $key === 'birthdate' ? $this->date($raw[$key]) : trim($this->scalar($raw[$key]));
            }
        }

        foreach (self::PHONE_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->phone($data[$field]);
            }
        }

        if (isset($data['category'])) {
            $data['category'] = $this->category($data['category']);
        }
        if (isset($data['sex'])) {
            $data['sex'] = $this->sex($data['sex']);
        }
        if (! empty($data['blood_type'])) {
            $bt = strtoupper(str_replace(' ', '', $data['blood_type']));
            $data['blood_type'] = $bt === 'UNKNOWN' ? 'Unknown' : $bt;
        }

        $category = $data['category'] ?? null;

        // SSCMS program_section held either a program or a section.
        if (array_key_exists('program_section', $raw) && trim($this->scalar($raw['program_section'])) !== '') {
            $value = trim($this->scalar($raw['program_section']));
            $lists = AcademicLists::forCategory($category);
            $inPrograms = in_array(mb_strtolower($value), array_map('mb_strtolower', $lists['programs']), true);
            if (empty($data['program_strand']) && $inPrograms) {
                $data['program_strand'] = $value;
            } elseif (empty($data['section'])) {
                $data['section'] = $value;
            }
        }

        foreach (['levels' => 'year_level', 'sections' => 'section', 'programs' => 'program_strand'] as $list => $attr) {
            if (! empty($data[$attr])) {
                $data[$attr] = AcademicLists::canonical($category, $list, $data[$attr]);
            }
        }

        return $data;
    }

    /** @return array<string,string> field => first error message */
    public function validate(array $data): array
    {
        $input = fn (string $key) => $data[$key] ?? null;

        $validator = Validator::make(
            array_map(fn ($v) => $v === '' ? null : $v, $data),
            PatientRules::rules(),
            PatientRules::messages()
        );
        $validator->after(PatientRules::academicCheck($input));

        $errors = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[$field] = $messages[0];
        }

        return $errors;
    }

    /** Existing (not archived) patients indexed by student ID and name key. */
    private function existingIndex(): array
    {
        $ids  = [];
        $keys = [];

        Patient::query()
            ->select(['id', 'patient_number', 'student_id', 'first_name', 'last_name', 'birthdate', 'category'])
            ->orderBy('id')
            ->chunk(1000, function ($patients) use (&$ids, &$keys) {
                foreach ($patients as $p) {
                    $label = "{$p->first_name} {$p->last_name} ({$p->patient_number})";
                    if ($p->student_id) {
                        $ids[mb_strtolower($p->student_id)] = $label;
                    }
                    $key = $this->nameKey([
                        'first_name' => $p->first_name,
                        'last_name'  => $p->last_name,
                        'birthdate'  => $p->birthdate?->format('Y-m-d'),
                        'category'   => $p->category,
                    ]);
                    if ($key) {
                        $keys[$key] = $label;
                    }
                }
            });

        return ['ids' => $ids, 'keys' => $keys];
    }

    /**
     * Duplicate key: first + last name + birthdate. Without a birthdate (SSCMS
     * data) the category stands in, so re-importing the same file is caught.
     */
    private function nameKey(array $data): ?string
    {
        $first = mb_strtolower(trim((string) ($data['first_name'] ?? '')));
        $last  = mb_strtolower(trim((string) ($data['last_name'] ?? '')));
        if ($first === '' || $last === '') {
            return null;
        }
        $birth = (string) ($data['birthdate'] ?? '');

        return $birth !== ''
            ? "{$first}|{$last}|b:{$birth}"
            : "{$first}|{$last}|c:".mb_strtolower((string) ($data['category'] ?? ''));
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            $value === null                    => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) && floor($value) === $value => (string) (int) $value,
            is_bool($value)                    => $value ? '1' : '0',
            default                            => (string) $value,
        };
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $value = trim($this->scalar($value));
        if ($value === '') {
            return null;
        }
        // Excel serial date that arrived as a number.
        if (ctype_digit($value) && (int) $value > 1000 && (int) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'Y/m/d', 'd-m-Y', 'M d, Y', 'F d, Y', 'F j, Y'] as $format) {
            try {
                $d = Carbon::createFromFormat('!'.$format, $value);
                if ($d && $d->format($format) === $value) {
                    return $d->format('Y-m-d');
                }
            } catch (\Throwable) {
                // try the next format
            }
        }

        return $value; // left as-is so validation reports it
    }

    private function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        // A number cell loses the leading 0 of 09XXXXXXXXX.
        if (strlen($digits) === 10 && str_starts_with($digits, '9') && $digits === $value) {
            return '0'.$digits;
        }

        return $value;
    }

    private function category(string $value): string
    {
        $labels = Patient::categoryLabels();
        $lower  = mb_strtolower(trim($value));

        foreach ($labels as $key => $label) {
            if ($lower === mb_strtolower((string) $key) || $lower === mb_strtolower((string) $label)) {
                return (string) $key;
            }
        }

        $alias = self::CATEGORY_ALIASES[$lower] ?? null;
        if ($alias && array_key_exists($alias, $labels)) {
            return $alias;
        }

        $slug = Str::slug($lower, '_');

        return array_key_exists($slug, $labels) ? $slug : $value;
    }

    private function sex(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $lower = ['m' => 'male', 'f' => 'female'][$lower] ?? $lower;

        foreach (Patient::sexLabels() as $key => $label) {
            if ($lower === mb_strtolower((string) $key) || $lower === mb_strtolower((string) $label)) {
                return (string) $key;
            }
        }

        return $value;
    }
}
