<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\PatientRepository;
use App\Services\AuditLogService;
use App\Services\Patients\PatientBulkService;
use App\Services\Patients\PatientExportService;
use App\Support\AcademicLists;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Export, bulk actions on selected patients and the year-end promotion wizard.
 */
class PatientBulkController extends Controller
{
    public const ACTIONS = [
        'move'       => 'update-patients',
        'activate'   => 'update-patients',
        'deactivate' => 'update-patients',
        'archive'    => 'delete-patients',
        'export'     => 'export-patients',
    ];

    public function __construct(
        private readonly PatientBulkService $bulk,
        private readonly PatientRepositoryInterface $patients,
    ) {}

    /* ------------------------------------------------------------------ */
    /*  EXPORT (current list filters)                                       */
    /* ------------------------------------------------------------------ */
    public function export(Request $request, PatientExportService $export)
    {
        $this->authorize('export-patients');

        $request->validate(['format' => ['nullable', Rule::in(PatientExportService::FORMATS)]]);

        $filters = array_filter($request->only(PatientRepository::FILTERS), fn ($v) => $v !== null && $v !== '');
        $query   = $this->patients->query($filters);
        $count   = (clone $query)->count();
        $format  = $request->query('format', 'csv');

        AuditLogService::log(
            action: 'exported',
            module: 'patients',
            description: "Exported {$count} patient(s) as ".strtoupper($format).($filters ? ' (filters: '.$this->describeFilters($filters).')' : ''),
            newValues: ['filters' => $filters, 'count' => $count, 'format' => $format],
        );

        return $export->download($query, $format);
    }

    /* ------------------------------------------------------------------ */
    /*  BULK ACTIONS on selected rows                                       */
    /* ------------------------------------------------------------------ */
    public function bulk(Request $request, PatientExportService $export)
    {
        $data = $request->validate([
            'action'         => ['required', Rule::in(array_keys(self::ACTIONS))],
            'ids'            => ['required', 'array', 'min:1', 'max:2000'],
            'ids.*'          => ['integer'],
            'format'         => ['nullable', Rule::in(PatientExportService::FORMATS)],
            'category'       => ['nullable', 'string', Rule::in(Patient::categories())],
            'year_level'     => ['nullable', 'string', 'max:50'],
            'section'        => ['nullable', 'string', 'max:50'],
            'program_strand' => ['nullable', 'string', 'max:100'],
            'clear_year_level'     => ['nullable', 'boolean'],
            'clear_section'        => ['nullable', 'boolean'],
            'clear_program_strand' => ['nullable', 'boolean'],
        ], [
            'ids.required' => 'Select at least one patient first.',
        ]);

        $this->authorize(self::ACTIONS[$data['action']]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        switch ($data['action']) {
            case 'export':
                $query  = Patient::withTrashed()->whereIn('id', $ids);
                $format = $data['format'] ?? 'csv';
                AuditLogService::log(
                    action: 'exported',
                    module: 'patients',
                    description: 'Exported '.count($ids).' selected patient(s) as '.strtoupper($format),
                    newValues: ['patient_ids' => $ids, 'format' => $format],
                );

                return $export->download($query, $format, 'patients-selected');

            case 'move':
                $category = $data['category'] ?? null;
                if (! $category && ! array_filter([$data['year_level'] ?? null, $data['section'] ?? null, $data['program_strand'] ?? null])
                    && empty($data['clear_year_level']) && empty($data['clear_section']) && empty($data['clear_program_strand'])) {
                    return back()->with('error', 'Choose a category, year level, section or program to move the selected patients to.');
                }
                // The new values must fit the target category (or each patient's own when unchanged).
                if ($category) {
                    $errors = AcademicLists::errors($category, $data);
                    if ($errors) {
                        return back()->withErrors($errors)->with('error', reset($errors));
                    }
                }
                $n = $this->bulk->move($ids, $data);

                return back()->with('success', "Moved {$n} patient(s).");

            case 'activate':
            case 'deactivate':
                $n = $this->bulk->setActive($ids, $data['action'] === 'activate');

                return back()->with('success', ($data['action'] === 'activate' ? 'Activated' : 'Deactivated')." {$n} patient(s).");

            case 'archive':
                $n = $this->bulk->archive($ids);

                return back()->with('success', "Archived {$n} patient(s). They can be restored from the Archived filter.");
        }

        return back();
    }

    /* ------------------------------------------------------------------ */
    /*  YEAR-END PROMOTION WIZARD                                           */
    /* ------------------------------------------------------------------ */
    public function promoteForm(Request $request)
    {
        $this->authorize('update-patients');

        $categories = Patient::categoryLabels();
        $category   = $request->query('category');
        $category   = array_key_exists((string) $category, $categories) ? $category : null;

        $levels  = [];
        $counts  = [];
        $mapping = [];
        if ($category) {
            $counts  = $this->bulk->levelCounts($category);
            $levels  = AcademicLists::forCategory($category)['levels'];
            // Levels found on patients but no longer in the list still get a row.
            foreach (array_keys($counts) as $lvl) {
                if (! in_array((string) $lvl, $levels, true)) {
                    $levels[] = (string) $lvl;
                }
            }
            $mapping = $this->bulk->defaultMapping($category);
        }

        return view('patients.promote.form', [
            'categories' => $categories,
            'category'   => $category,
            'levels'     => $levels,
            'counts'     => $counts,
            'mapping'    => $mapping,
            'targets'    => $this->targets(),
        ]);
    }

    public function promotePreview(Request $request)
    {
        $this->authorize('update-patients');

        [$category, $mapping, $clear] = $this->validatedPromotion($request);
        $plan = $this->bulk->plan($category, $mapping);

        return view('patients.promote.preview', [
            'categories' => Patient::categoryLabels(),
            'category'   => $category,
            'mapping'    => $mapping,
            'clear'      => $clear,
            'plan'       => $plan,
            'total'      => array_sum(array_map(fn ($s) => count($s['ids']), $plan)),
        ]);
    }

    public function promote(Request $request)
    {
        $this->authorize('update-patients');

        [$category, $mapping, $clear] = $this->validatedPromotion($request);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the confirmation box to continue.']);

        $plan = $this->bulk->plan($category, $mapping);
        if (! $plan) {
            return redirect()->route('patients.promote.form', ['category' => $category])
                ->with('error', 'Nothing to promote: no active patients match the selected levels.');
        }

        $n = $this->bulk->promote($category, $plan, $clear);

        return redirect()->route('patients.index', ['category' => $category])
            ->with('success', "Promotion complete: {$n} patient(s) updated.");
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */
    private function validatedPromotion(Request $request): array
    {
        $data = $request->validate([
            'category'   => ['required', Rule::in(Patient::categories())],
            'mapping'    => ['required', 'array'],
            'mapping.*'  => ['nullable', 'string', 'max:200'],
            'from'       => ['nullable', 'array'],
            'from.*'     => ['nullable', 'string', 'max:100'],
            'clear_sections' => ['nullable', 'boolean'],
        ]);

        // Level names can contain characters that are awkward as array keys in
        // a form, so the form posts from[i] + mapping[i] pairs.
        $mapping = [];
        foreach ($data['mapping'] as $i => $target) {
            if (! array_key_exists($i, $data['from'] ?? [])) {
                continue;
            }
            // Empty strings arrive as null (ConvertEmptyStringsToNull): "" = no year level.
            $from = (string) ($data['from'][$i] ?? '');
            $target = (string) $target;
            if ($target !== '' && ! array_key_exists($target, $this->flatTargets())) {
                abort(422, 'Invalid promotion target.');
            }
            $mapping[(string) $from] = $target;
        }

        return [$data['category'], $mapping, (bool) ($data['clear_sections'] ?? false)];
    }

    /** Grouped options: category label => ["category|level" => level]. */
    private function targets(): array
    {
        $out = [];
        foreach (Patient::categoryLabels() as $value => $label) {
            $levels = AcademicLists::forCategory($value)['levels'];
            foreach ($levels as $level) {
                $out[$label][$value.'|'.$level] = $level;
            }
            if ($levels === []) {
                $out[$label][$value.'|'] = $label.' (no year level)';
            }
        }

        return $out;
    }

    private function flatTargets(): array
    {
        return array_merge(...array_values($this->targets()) ?: [[]]);
    }

    private function describeFilters(array $filters): string
    {
        $labels = Patient::categoryLabels();

        return collect($filters)
            ->except(['sort', 'dir'])
            ->map(fn ($v, $k) => $k === 'category' ? 'category '.($labels[$v] ?? $v) : str_replace('_', ' ', $k).' '.$v)
            ->implode(', ');
    }
}
