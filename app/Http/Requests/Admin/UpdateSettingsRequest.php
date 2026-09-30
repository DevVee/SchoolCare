<?php

namespace App\Http\Requests\Admin;

use App\Services\ImageResizer;
use App\Services\SettingsService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates one settings group (the {group} route parameter). Only the fields
 * registered for that group in config/settings.php are validated or saved.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    public function group(): string
    {
        return (string) $this->route('group');
    }

    /** Field definitions for the submitted group. */
    public function fields(): array
    {
        return app(SettingsService::class)->fields($this->group());
    }

    public function rules(): array
    {
        $rules   = [];
        $globals = config('settings.sms_globals', []);

        foreach ($this->fields() as $key => $def) {
            $fieldRules = $def['rules'] ?? ['nullable'];

            switch ($def['type']) {
                case 'boolean':
                    // Unchecked boxes are absent; the controller reads them with boolean().
                    $fieldRules = ['nullable', 'boolean'];
                    break;

                case 'image':
                    $rules["remove_{$key}"] = ['nullable', 'boolean'];
                    // Sniff the real content: an SVG/HTML file renamed to .png is rejected.
                    $fieldRules[] = function (string $attribute, mixed $value, Closure $fail) {
                        if (! $value instanceof \Illuminate\Http\UploadedFile || ! $value->isValid()) {
                            return;
                        }
                        $mime = @(new \finfo(FILEINFO_MIME_TYPE))->file($value->getRealPath());
                        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
                        if (! in_array($mime, $allowed, true)) {
                            $fail('The :attribute must be a PNG, JPG, WebP or ICO image.');
                        }
                    };
                    if (! empty($def['resize'])) {
                        // Resized on save (ImageResizer): it must decode, and not be so big that decoding runs out of memory.
                        $fieldRules[] = function (string $attribute, mixed $value, Closure $fail) {
                            if (! $value instanceof \Illuminate\Http\UploadedFile || ! $value->isValid()) {
                                return;
                            }
                            $size = @getimagesize($value->getRealPath());
                            if (! $size || $size[0] < 1 || $size[1] < 1) {
                                $fail('The :attribute could not be read. Save it again as PNG or JPG and upload it again.');
                            } elseif ($size[0] * $size[1] > ImageResizer::MAX_PIXELS) {
                                $fail('The :attribute is too large. Use an image smaller than 6000 by 6000 pixels.');
                            }
                        };
                    }
                    break;

                case 'secret':
                    $rules["remove_{$key}"] = ['nullable', 'boolean'];
                    break;

                case 'select':
                    $fieldRules[] = function (string $attribute, mixed $value, Closure $fail) use ($def) {
                        $options = $def['options'] ?? [];
                        if (is_array($options) && $value !== null && $value !== '' && ! array_key_exists($value, $options)) {
                            $fail('The selected :attribute is invalid.');
                        }
                    };
                    break;
            }

            if (! empty($def['placeholders'])) {
                $allowed = array_merge($def['placeholders'], $globals);
                $fieldRules[] = function (string $attribute, mixed $value, Closure $fail) use ($allowed) {
                    preg_match_all('/\{([a-z_]+)\}/i', (string) $value, $m);
                    $unknown = array_diff(array_unique($m[1]), $allowed);
                    if ($unknown) {
                        $fail('Unknown placeholder(s): {'.implode('}, {', $unknown).'}. Allowed: {'.implode('}, {', $allowed).'}.');
                    }
                };
            }

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $service = app(SettingsService::class);

                foreach ($this->fields() as $key => $def) {
                    if (! in_array($def['type'], ['json_list', 'options'], true) || ! $this->has($key)) {
                        continue;
                    }

                    $items = $def['type'] === 'options'
                        ? $service->toOptions((string) $this->input($key))
                        : $service->toList((string) $this->input($key));

                    if (count($items) > 300) {
                        $validator->errors()->add($key, "{$def['label']}: at most 300 entries.");
                    }

                    $required = in_array('required', $def['rules'] ?? [], true);
                    if ($required && count($items) === 0) {
                        $validator->errors()->add($key, "{$def['label']} needs at least one entry.");
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        $out = [];
        foreach ($this->fields() as $key => $def) {
            $out[$key] = $def['label'] ?? $key;
        }

        return $out;
    }
}
