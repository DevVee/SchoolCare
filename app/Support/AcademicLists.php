<?php

namespace App\Support;

/**
 * Year level / section / program choices, optionally per patient category
 * (SSCMS grade_years + program_sections were linked to a category).
 *
 * Source: settings('academic_levels_by_category') JSON
 *   {"college": {"levels": [...], "sections": [...], "programs": [...]}, ...}
 * A category missing from the map (or a missing key) falls back to the flat
 * lists year_levels / sections / program_strands. An empty list means the
 * field does not apply to that category (only an empty value is accepted).
 */
class AcademicLists
{
    public const FIELDS = [
        'levels'   => ['setting' => 'year_levels',     'attribute' => 'year_level'],
        'sections' => ['setting' => 'sections',        'attribute' => 'section'],
        'programs' => ['setting' => 'program_strands', 'attribute' => 'program_strand'],
    ];

    /** Parsed per-category map (invalid JSON = empty map). */
    public static function map(): array
    {
        $raw = settings('academic_levels_by_category', '');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $category => $lists) {
            if (! is_array($lists)) {
                continue;
            }
            foreach (array_keys(self::FIELDS) as $key) {
                if (array_key_exists($key, $lists) && is_array($lists[$key])) {
                    $out[(string) $category][$key] = array_values(array_unique(array_filter(
                        array_map(fn ($v) => trim((string) $v), $lists[$key]),
                        fn ($v) => $v !== ''
                    )));
                }
            }
        }

        return $out;
    }

    /** Flat (category independent) lists. */
    public static function flat(): array
    {
        $out = [];
        foreach (self::FIELDS as $key => $meta) {
            $out[$key] = settings()->list($meta['setting']);
        }

        return $out;
    }

    /**
     * Lists that apply to a category: ['levels' => [...], 'sections' => [...], 'programs' => [...]].
     * A null / unknown category gets the flat lists.
     */
    public static function forCategory(?string $category): array
    {
        $flat = self::flat();
        $map  = $category !== null && $category !== '' ? (self::map()[$category] ?? null) : null;

        if ($map === null) {
            return $flat;
        }

        $out = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $out[$key] = array_key_exists($key, $map) ? $map[$key] : $flat[$key];
        }

        return $out;
    }

    /** Everything the patient form needs to filter selects client-side. */
    public static function clientConfig(): array
    {
        $categories = array_keys(settings()->options('patient_categories'));
        $byCategory = [];
        foreach ($categories as $category) {
            $byCategory[$category] = self::forCategory($category);
        }

        return ['flat' => self::flat(), 'byCategory' => $byCategory];
    }

    /**
     * Validation errors for academic values against the category's lists.
     * $current holds the record's stored values (always accepted, so legacy
     * values never block an unrelated edit).
     *
     * @return array<string,string> attribute => message
     */
    public static function errors(?string $category, array $values, array $current = []): array
    {
        $lists  = self::forCategory($category);
        $label  = $category ? (settings()->options('patient_categories')[$category] ?? $category) : 'this category';
        $errors = [];

        foreach (self::FIELDS as $key => $meta) {
            $attr  = $meta['attribute'];
            $value = trim((string) ($values[$attr] ?? ''));

            if ($value === '' || $value === trim((string) ($current[$attr] ?? ''))) {
                continue;
            }

            $allowed = $lists[$key];
            if (! in_array(mb_strtolower($value), array_map('mb_strtolower', $allowed), true)) {
                $name = ['levels' => 'Year level', 'sections' => 'Section', 'programs' => 'Program'][$key];
                $errors[$attr] = $allowed === []
                    ? "{$name} does not apply to {$label}. Leave it empty."
                    : "{$name} \"{$value}\" is not offered for {$label}. Choose one of: ".implode(', ', array_slice($allowed, 0, 12)).(count($allowed) > 12 ? ', ...' : '').'.';
            }
        }

        return $errors;
    }

    /** Case-insensitive match to the canonical spelling in the list (for imports). */
    public static function canonical(?string $category, string $key, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (self::forCategory($category)[$key] ?? [] as $option) {
            if (mb_strtolower($option) === mb_strtolower($value)) {
                return $option;
            }
        }

        return $value;
    }
}
