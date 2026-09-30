<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuditLogService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves the submitted fields of one settings group (validated beforehand by
 * UpdateSettingsRequest or a subclass): booleans, uploaded images and plain
 * values, then writes one audit log entry for what changed.
 *
 * Used by Admin → Settings and Admin → Website.
 */
trait SavesSettingsGroup
{
    /**
     * @param  array<string,array>  $fields  field definitions to save (key => def)
     * @return array<string,array{old:?string,new:?string}> the keys whose value changed
     */
    protected function saveSettingsFields(Request $request, SettingsService $settings, string $group, array $fields): array
    {
        $values = [];
        $newFiles = [];
        $oldFiles = [];

        foreach ($fields as $key => $def) {
            switch ($def['type']) {
                case 'boolean':
                    // Booleans of THIS group only: an unchecked box means false.
                    $values[$key] = $request->boolean($key);
                    break;

                case 'image':
                    $current = (string) $settings->get($key, '');

                    if ($request->hasFile($key)) {
                        $file = $request->file($key);
                        $ext  = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
                        $name = $key.'-'.Str::random(20).'.'.$ext;
                        $path = $file->storeAs('branding', $name, 'public');

                        $values[$key] = $path;
                        $newFiles[]   = $path;
                        if ($current !== '') {
                            $oldFiles[] = $current;
                        }
                    } elseif ($request->boolean("remove_{$key}")) {
                        $values[$key] = '';
                        if ($current !== '') {
                            $oldFiles[] = $current;
                        }
                    }
                    break;

                case 'secret':
                    // The form never shows the stored value: an empty box keeps it,
                    // a typed value replaces it, and "remove" clears it.
                    if ($request->boolean("remove_{$key}")) {
                        $values[$key] = '';
                    } elseif (trim((string) $request->input($key)) !== '') {
                        $values[$key] = trim((string) $request->input($key));
                    }
                    break;

                default:
                    // Whitelisted keys only; fields not present in the form are left untouched.
                    if ($request->has($key)) {
                        $values[$key] = $request->input($key);
                    }
            }
        }

        try {
            $changed = $settings->setMany($values);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($newFiles);
            throw $e;
        }

        // Only delete replaced/removed uploads after the new values are saved.
        foreach ($oldFiles as $old) {
            if (str_starts_with($old, 'branding/')) {
                Storage::disk('public')->delete($old);
            }
        }

        if ($changed) {
            AuditLogService::log(
                action: 'updated',
                module: 'settings',
                description: 'Updated '.($settings->groups()[$group]['label'] ?? $group).' settings: '.implode(', ', array_keys($changed)),
                oldValues: $this->settingsAuditValues($changed, 'old', $fields),
                newValues: $this->settingsAuditValues($changed, 'new', $fields),
            );
        }

        return $changed;
    }

    /** Old/new values for the audit log. Long text is truncated. */
    protected function settingsAuditValues(array $changed, string $side, array $fields): array
    {
        $out = [];
        foreach ($changed as $key => $pair) {
            if (! empty($fields[$key]['secret']) || ($fields[$key]['type'] ?? null) === 'secret') {
                $out[$key] = '[hidden]';
                continue;
            }
            $out[$key] = Str::limit((string) ($pair[$side] ?? ''), 300);
        }

        return $out;
    }
}
