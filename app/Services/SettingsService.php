<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Typed access to admin-editable settings.
 *
 * Definitions (type, default, rules) live in config/settings.php; values live
 * in the `settings` table. Reads fall back to the config default when a row is
 * missing, so a fresh or partially-seeded database behaves like the defaults.
 *
 * Registered as a *scoped* instance (see AppServiceProvider) so the in-memory
 * copy is discarded between requests and between queued jobs.
 */
class SettingsService
{
    public const CACHE_KEY = 'settings.values.v2';
    private const CACHE_TTL = 600;

    /** @var array<string,string|null>|null raw DB values keyed by setting key */
    private ?array $values = null;

    /** @var array<string,array>|null flat definitions keyed by setting key */
    private ?array $definitions = null;

    // ─── Definitions ─────────────────────────────────────────────────────────

    /** Group meta keyed by group name (label, icon, description, fields...). */
    public function groups(): array
    {
        return config('settings.groups', []);
    }

    /**
     * Groups that have at least one field (these appear in the Settings nav).
     * 'standalone' groups (e.g. the public website) are edited on their own
     * admin page with their own permission, so they are left out here.
     */
    public function visibleGroups(): array
    {
        return array_filter($this->groups(), fn ($g) => ! empty($g['fields']) && empty($g['standalone']));
    }

    public function hasGroup(string $group): bool
    {
        return array_key_exists($group, $this->groups());
    }

    /** Field definitions for one group, keyed by setting key. */
    public function fields(string $group): array
    {
        return $this->groups()[$group]['fields'] ?? [];
    }

    /** All field definitions keyed by setting key (each with a 'group' entry). */
    public function definitions(): array
    {
        if ($this->definitions === null) {
            $this->definitions = [];
            foreach ($this->groups() as $group => $meta) {
                foreach ($meta['fields'] ?? [] as $key => $def) {
                    $this->definitions[$key] = $def + ['group' => $group];
                }
            }
        }

        return $this->definitions;
    }

    public function definition(string $key): ?array
    {
        return $this->definitions()[$key] ?? null;
    }

    // ─── Reads ───────────────────────────────────────────────────────────────

    /**
     * Get a typed setting value.
     * Order: stored value → config default → $default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $def = $this->definition($key);
        $raw = $this->raw();

        if (! array_key_exists($key, $raw)) {
            if ($def && array_key_exists('default', $def)) {
                return $this->normalizeDefault($def);
            }

            return $default;
        }

        $value = $raw[$key];

        if ($def) {
            return $this->decode($value, $def);
        }

        // Legacy/unregistered key: decode by the stored DB type.
        return $this->decodeLegacy($key, $value) ?? $default;
    }

    /** All values of a group as [key => typed value]. */
    public function group(string $group): array
    {
        $out = [];
        foreach (array_keys($this->fields($group)) as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    /** A json_list setting as a clean list of strings. */
    public function list(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    /**
     * An options setting as [value => label]. json_list settings are returned
     * as [item => item] so callers can treat both the same way.
     */
    public function options(string $key): array
    {
        $value = $this->get($key, []);
        if (! is_array($value)) {
            return [];
        }

        return array_is_list($value) ? array_combine($value, $value) ?: [] : $value;
    }

    /** Public URL for an image setting, or the fallback when none is uploaded. */
    public function imageUrl(string $key, ?string $fallback = null): string
    {
        $path = (string) $this->get($key, '');
        $definition = $this->definition($key);
        $fallback ??= $definition['fallback'] ?? '';

        if ($path === '') {
            // e.g. the favicon falls back to the uploaded school logo before the default mark
            if (! empty($definition['fallback_key']) && (string) $this->get($definition['fallback_key'], '') !== '') {
                return $this->imageUrl($definition['fallback_key']);
            }

            return $fallback;
        }

        // asset() follows the host the page was requested on (127.0.0.1 vs localhost,
        // custom domains, per-school subdomains) instead of the fixed APP_URL.
        return asset('storage/'.ltrim($path, '/'));
    }

    /**
     * Absolute local filesystem path for an image setting (for dompdf), or the
     * public/ path of the fallback. Returns null when no readable file exists.
     */
    public function imagePath(string $key, bool $withFallback = true): ?string
    {
        $path = (string) $this->get($key, '');

        if ($path !== '') {
            try {
                $abs = Storage::disk('public')->path($path);
                if (is_file($abs)) {
                    return $abs;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        if ($withFallback) {
            $fallback = $this->definition($key)['fallback'] ?? null;
            if ($fallback && is_file(public_path(ltrim($fallback, '/')))) {
                return public_path(ltrim($fallback, '/'));
            }
        }

        return null;
    }

    // ─── Writes ──────────────────────────────────────────────────────────────

    /**
     * Save many settings at once (one transaction, one cache bust).
     * Unknown keys are ignored. Returns [key => ['old' => raw, 'new' => raw]]
     * for the keys whose stored value actually changed.
     */
    public function setMany(array $values): array
    {
        $defs    = $this->definitions();
        $current = $this->raw(fresh: true);
        $changed = [];

        DB::transaction(function () use ($values, $defs, $current, &$changed) {
            foreach ($values as $key => $value) {
                if (! isset($defs[$key])) {
                    continue;
                }

                $def     = $defs[$key];
                $encoded = $this->encode($value, $def);
                $stored  = array_key_exists($key, $current);
                // Effective previous value: stored row, else the config default.
                $old     = $stored ? $current[$key] : $this->encode($def['default'] ?? null, $def);

                if ($stored && $old === $encoded) {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value'       => $encoded,
                        'type'        => $this->storageType($def['type']),
                        'group'       => $def['group'],
                        'description' => Str::limit($def['label'] ?? $key, 250, ''),
                    ]
                );

                if ($old !== $encoded) {
                    $changed[$key] = ['old' => $old, 'new' => $encoded];
                }
            }
        });

        $this->flush();

        return $changed;
    }

    /**
     * Save a single setting. Unregistered keys are stored raw for backwards
     * compatibility with the old Setting::set() API.
     */
    public function set(string $key, mixed $value): void
    {
        if ($this->definition($key)) {
            $this->setMany([$key => $value]);

            return;
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );

        $this->flush();
    }

    /**
     * Insert rows for registered keys that are missing (never overwrites an
     * existing value) and refresh group/type/description metadata.
     * Returns the number of rows inserted.
     */
    public function syncDefaults(): int
    {
        $existing = Setting::query()->get()->keyBy('key');
        $inserted = 0;

        DB::transaction(function () use ($existing, &$inserted) {
            foreach ($this->definitions() as $key => $def) {
                $meta = [
                    'type'        => $this->storageType($def['type']),
                    'group'       => $def['group'],
                    'description' => Str::limit($def['label'] ?? $key, 250, ''),
                ];

                $row = $existing->get($key);

                if (! $row) {
                    Setting::query()->create($meta + [
                        'key'   => $key,
                        'value' => $this->encode($def['default'] ?? null, $def),
                    ]);
                    $inserted++;
                    continue;
                }

                if ($row->type !== $meta['type'] || $row->group !== $meta['group'] || $row->description !== $meta['description']) {
                    // Metadata only — the stored value is never touched.
                    Setting::query()->whereKey($row->id)->update($meta);
                }
            }
        });

        $this->flush();

        return $inserted;
    }

    /** Forget cached values (in-memory and shared cache). */
    public function flush(): void
    {
        $this->values = null;

        try {
            Cache::forget(self::CACHE_KEY);
            Cache::forget('sscms_settings_all'); // legacy key used by the old Setting model
        } catch (\Throwable) {
            // Cache store unavailable — nothing to bust.
        }
    }

    // ─── Encoding helpers ────────────────────────────────────────────────────

    /** Convert a form/user value into the string stored in the DB. */
    public function encode(mixed $value, array $def): ?string
    {
        return match ($def['type']) {
            'boolean'   => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'integer'   => (string) (int) $value,
            'json_list' => json_encode($this->toList($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'options'   => json_encode((object) $this->toOptions($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            // API keys and the like: stored encrypted with the app key, '' when not set.
            'secret'    => trim((string) $value) === '' ? '' : Crypt::encryptString(trim((string) $value)),
            default     => $value === null ? '' : (string) $value,
        };
    }

    /** A secret setting in plain text, or '' when unset or unreadable (e.g. the app key changed). */
    private function decrypt(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            Log::warning('A secret setting could not be decrypted (was APP_KEY changed?).');

            return '';
        }
    }

    /** Parse a list value: array, JSON array string, or newline-separated text. */
    public function toList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n/', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '' && ! in_array($item, $items, true)) {
                $items[] = Str::limit($item, 150, '');
            }
        }

        return $items;
    }

    /**
     * Parse an options value into [value => label]. Accepts an associative
     * array, a JSON object/list, or text lines "value | Label" (a line with
     * only a label gets a slug value).
     */
    public function toOptions(mixed $value, array $knownLabels = []): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $lines = preg_split('/\r\n|\r|\n/', $value);
                $value = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (str_contains($line, '|')) {
                        [$v, $l] = array_map('trim', explode('|', $line, 2));
                    } else {
                        [$v, $l] = [Str::slug($line, '_'), $line];
                    }
                    if ($v === '') {
                        $v = Str::slug($l, '_');
                    }
                    $value[$v] = $l !== '' ? $l : $v;
                }
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        if (array_is_list($value)) {
            // Legacy list of values (e.g. ["college","senior_high"]).
            foreach ($value as $item) {
                if (! is_scalar($item) || trim((string) $item) === '') {
                    continue;
                }
                $item = trim((string) $item);
                $out[$item] = $knownLabels[$item] ?? Str::headline($item);
            }
        } else {
            foreach ($value as $v => $l) {
                $v = trim((string) $v);
                if ($v === '') {
                    continue;
                }
                $out[Str::limit($v, 60, '')] = Str::limit(trim((string) (is_scalar($l) ? $l : $v)) ?: $v, 150, '');
            }
        }

        return $out;
    }

    /** Text representation used to pre-fill textareas for list/options fields. */
    public function toText(string $key): string
    {
        $def = $this->definition($key);
        $value = $this->get($key, []);

        if (($def['type'] ?? null) === 'options') {
            return collect((array) $value)->map(fn ($l, $v) => "{$v} | {$l}")->implode("\n");
        }

        return implode("\n", (array) $value);
    }

    private function decode(?string $value, array $def): mixed
    {
        return match ($def['type']) {
            'boolean'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer'   => is_numeric($value) ? (int) $value : (int) ($def['default'] ?? 0),
            'json_list' => $this->toList($value ?? '[]'),
            'options'   => $this->toOptions($value ?? '{}', is_array($def['default'] ?? null) ? $def['default'] : []),
            'secret'    => $this->decrypt($value),
            default     => $value ?? '',
        };
    }

    private function normalizeDefault(array $def): mixed
    {
        $default = $def['default'] ?? null;

        return match ($def['type']) {
            'boolean'   => (bool) $default,
            'integer'   => (int) $default,
            'json_list' => $this->toList($default ?? []),
            'options'   => $this->toOptions($default ?? []),
            default     => $default ?? '',
        };
    }

    private function decodeLegacy(string $key, ?string $value): mixed
    {
        $type = $this->types[$key] ?? 'string';

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json'    => json_decode((string) $value, true),
            default   => $value,
        };
    }

    private function storageType(string $type): string
    {
        return match ($type) {
            'boolean'             => 'boolean',
            'integer'             => 'integer',
            'json_list', 'options' => 'json',
            'text'                => 'text',
            'secret'              => 'encrypted',
            default               => 'string',
        };
    }

    /** @var array<string,string> stored DB type per key (for legacy decoding) */
    private array $types = [];

    /**
     * Raw stored values keyed by setting key. Cached; never caches a failure
     * (e.g. before migrations have run), so it recovers once the table exists.
     */
    private function raw(bool $fresh = false): array
    {
        if (! $fresh && $this->values !== null) {
            return $this->values;
        }

        try {
            $rows = $fresh
                ? $this->loadRows()
                : Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->loadRows());
        } catch (\Throwable) {
            return [];
        }

        $this->types  = Arr::pluck($rows, 'type', 'key');
        $this->values = Arr::pluck($rows, 'value', 'key');

        return $this->values;
    }

    private function loadRows(): array
    {
        return Setting::query()->get(['key', 'value', 'type'])
            ->map(fn ($s) => ['key' => $s->key, 'value' => $s->value, 'type' => $s->type])
            ->all();
    }
}
