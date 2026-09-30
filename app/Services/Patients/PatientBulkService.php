<?php

namespace App\Services\Patients;

use App\Models\Patient;
use App\Services\AuditLogService;
use App\Support\AcademicLists;
use Illuminate\Support\Facades\DB;

/**
 * Bulk actions on the patient list (SSCMS "Move" modal and bulk delete) and
 * the year-end promotion wizard. Every action runs in one transaction and
 * writes a single summary audit log entry instead of one entry per patient.
 */
class PatientBulkService
{
    /**
     * Move patients to a category / year level / section / program.
     * Fields left null are not changed; clear_* flags empty a field.
     *
     * @param  int[]  $ids
     */
    public function move(array $ids, array $changes): int
    {
        $set = [];
        foreach (['category', 'year_level', 'section', 'program_strand'] as $field) {
            if (! empty($changes['clear_'.$field])) {
                $set[$field] = null;
            } elseif (isset($changes[$field]) && $changes[$field] !== '') {
                $set[$field] = $changes[$field];
            }
        }
        if ($set === []) {
            return 0;
        }
        $set['updated_by'] = auth()->id();

        $count = DB::transaction(fn () => Patient::whereIn('id', $ids)->update($set + ['updated_at' => now()]));

        $this->log('bulk_moved', "Moved {$count} patient(s): ".$this->describe($set), $ids, $set);

        return $count;
    }

    /** @param int[] $ids */
    public function setActive(array $ids, bool $active): int
    {
        $count = DB::transaction(fn () => Patient::whereIn('id', $ids)
            ->update(['is_active' => $active, 'updated_by' => auth()->id(), 'updated_at' => now()]));

        $this->log($active ? 'bulk_activated' : 'bulk_deactivated',
            ($active ? 'Activated' : 'Deactivated')." {$count} patient(s)", $ids);

        return $count;
    }

    /** Soft delete (archive). @param int[] $ids */
    public function archive(array $ids): int
    {
        $count = DB::transaction(function () use ($ids) {
            $n = 0;
            Patient::withoutEvents(function () use ($ids, &$n) {
                foreach (Patient::whereIn('id', $ids)->get() as $patient) {
                    $patient->delete();
                    $n++;
                }
            });

            return $n;
        });

        $this->log('bulk_archived', "Archived {$count} patient(s)", $ids);

        return $count;
    }

    // ─── Year-end promotion ──────────────────────────────────────────────────

    /**
     * Active patients per year level for a category (for the wizard).
     *
     * @return array<string,int> level => count ('' = no level)
     */
    public function levelCounts(string $category): array
    {
        return Patient::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->selectRaw("COALESCE(year_level, '') as lvl, count(*) as n")
            ->groupBy('lvl')
            ->pluck('n', 'lvl')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Default "to" target for each level of a category: the next level in the
     * list; the last level stays (the user can pick another category).
     *
     * @return array<string,string> from level => "category|level" or "" (no change)
     */
    public function defaultMapping(string $category): array
    {
        $levels = AcademicLists::forCategory($category)['levels'];
        $out = [];
        foreach ($levels as $i => $level) {
            $out[$level] = isset($levels[$i + 1]) ? $category.'|'.$levels[$i + 1] : '';
        }

        return $out;
    }

    /**
     * Resolve a mapping into concrete moves (ids are captured BEFORE anything
     * is updated, so 7 -> 8 and 8 -> 9 in the same run never cascade).
     *
     * @param  array<string,string>  $mapping  from level => "category|level" ("" = no change)
     * @return array<int, array{from:string, to_category:string, to_level:string, ids:int[]}>
     */
    public function plan(string $category, array $mapping): array
    {
        $plan = [];
        foreach ($mapping as $from => $target) {
            $target = (string) $target;
            if ($target === '' || ! str_contains($target, '|')) {
                continue;
            }
            [$toCategory, $toLevel] = explode('|', $target, 2);
            if (! array_key_exists($toCategory, Patient::categoryLabels())) {
                continue;
            }
            if ($toCategory === $category && $toLevel === (string) $from) {
                continue;
            }

            $ids = Patient::query()
                ->where('category', $category)
                ->where('is_active', true)
                ->when((string) $from === '', fn ($q) => $q->where(fn ($w) => $w->whereNull('year_level')->orWhere('year_level', '')),
                    fn ($q) => $q->where('year_level', (string) $from))
                ->pluck('id')
                ->all();

            if ($ids) {
                $plan[] = ['from' => (string) $from, 'to_category' => $toCategory, 'to_level' => $toLevel, 'ids' => $ids];
            }
        }

        return $plan;
    }

    /** Apply a plan in one transaction. Returns the number of patients moved. */
    public function promote(string $category, array $plan, bool $clearSections): int
    {
        $total = 0;

        DB::transaction(function () use ($category, $plan, $clearSections, &$total) {
            foreach ($plan as $step) {
                $set = [
                    'category'   => $step['to_category'],
                    'year_level' => $step['to_level'] !== '' ? $step['to_level'] : null,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ];
                if ($clearSections) {
                    $set['section'] = null;
                }
                if ($step['to_category'] !== $category) {
                    // Programs / strands belong to a category (an SHS strand is not a college course).
                    $set['program_strand'] = null;
                    // A category with a single section (e.g. Alumni) gets it automatically.
                    $sections = AcademicLists::forCategory($step['to_category'])['sections'];
                    if (count($sections) === 1) {
                        $set['section'] = $sections[0];
                    }
                }
                foreach (array_chunk($step['ids'], 500) as $ids) {
                    $total += Patient::whereIn('id', $ids)->update($set);
                }
            }
        });

        $labels = Patient::categoryLabels();
        $lines  = array_map(fn ($s) => ($s['from'] !== '' ? $s['from'] : 'No level').' to '
            .($labels[$s['to_category']] ?? $s['to_category']).' '.$s['to_level'].' ('.count($s['ids']).')', $plan);

        AuditLogService::log(
            action: 'promoted',
            module: 'patients',
            description: "Year-end promotion of {$total} patient(s) in ".($labels[$category] ?? $category).': '.implode('; ', $lines)
                .($clearSections ? '. Sections cleared.' : '.'),
            newValues: [
                'category'       => $category,
                'clear_sections' => $clearSections,
                'steps'          => array_map(fn ($s) => [
                    'from' => $s['from'], 'to_category' => $s['to_category'], 'to_level' => $s['to_level'], 'count' => count($s['ids']),
                ], $plan),
            ],
        );

        return $total;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function describe(array $set): string
    {
        $labels = Patient::categoryLabels();
        $parts  = [];
        foreach (['category' => 'category', 'year_level' => 'year level', 'section' => 'section', 'program_strand' => 'program'] as $field => $name) {
            if (array_key_exists($field, $set)) {
                $value = $set[$field] === null ? '(cleared)' : ($field === 'category' ? ($labels[$set[$field]] ?? $set[$field]) : $set[$field]);
                $parts[] = "{$name} {$value}";
            }
        }

        return implode(', ', $parts);
    }

    private function log(string $action, string $description, array $ids, array $values = []): void
    {
        unset($values['updated_by']);

        AuditLogService::log(
            action: $action,
            module: 'patients',
            description: $description,
            newValues: $values + ['patient_ids' => array_values(array_map('intval', $ids))],
        );
    }
}
