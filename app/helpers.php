<?php

use App\Services\SettingsService;

if (! function_exists('settings')) {
    /**
     * Read an admin-editable setting, or get the SettingsService when no key
     * is given: settings('clinic_name'), settings()->imageUrl('brand_logo').
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        return $key === null ? $service : $service->get($key, $default);
    }
}
