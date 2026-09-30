<?php

namespace App\Http\Requests\Admin;

use App\Services\LandingContent;

/**
 * Validates the "Page content" tab of Administration → Website: the fields of
 * the 'landing' settings group, with the manage-landing permission instead of
 * manage-settings.
 */
class UpdateLandingSettingsRequest extends UpdateSettingsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-landing') ?? false;
    }

    public function group(): string
    {
        return 'landing';
    }

    protected function prepareForValidation(): void
    {
        // Keep only known section keys, in the submitted order, one per line.
        if ($this->has('landing_section_order')) {
            $submitted = preg_split('/[\r\n,]+/', (string) $this->input('landing_section_order')) ?: [];
            $known = array_keys(LandingContent::SECTIONS);
            $order = array_values(array_unique(array_intersect(array_map('trim', $submitted), $known)));
            $order = array_values(array_unique(array_merge($order, $known)));

            $this->merge(['landing_section_order' => implode("\n", $order)]);
        }
    }
}
