<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuditLogService;
use App\Services\ImageResizer;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        try {
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

                            if (! empty($def['resize'])) {
                                // Printing images: made smaller and re-saved without metadata.
                                $path = $this->storeResizedImage($file->getRealPath(), $key, (array) $def['resize']);
                            } else {
                                $ext  = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
                                $name = $key.'-'.Str::random(20).'.'.$ext;
                                $path = $file->storeAs('branding', $name, 'public');
                            }

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

            $changed = $settings->setMany($values);
        } catch (\Throwable $e) {
            // Nothing was saved: drop the files uploaded by this request.
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

    /**
     * Resize an uploaded image (keeping its shape and transparency, without metadata)
     * and store it on the public disk under branding/. Returns the stored path.
     *
     * @param  array{0?:int,1?:int}  $box  [max width, max height] in pixels
     *
     * @throws ValidationException when the image cannot be processed
     */
    protected function storeResizedImage(string $sourcePath, string $key, array $box): string
    {
        [$maxWidth, $maxHeight] = array_values($box) + [2400, 2400];

        try {
            $image = app(ImageResizer::class)->fit($sourcePath, (int) $maxWidth, (int) $maxHeight);
        } catch (\RuntimeException) {
            throw ValidationException::withMessages([
                $key => 'This image could not be processed. Save it again as PNG or JPG and upload it again.',
            ]);
        }

        $path = 'branding/'.$key.'-'.Str::random(20).'.'.$image['extension'];
        Storage::disk('public')->put($path, $image['contents']);

        return $path;
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
