<?php

namespace App\Models;

use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;

/**
 * Settings row. Reading and writing should go through settings() /
 * SettingsService; the static helpers below are kept as thin wrappers for
 * backwards compatibility.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    /** Bust the settings cache. */
    public static function bustCache(): void
    {
        app(SettingsService::class)->flush();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        app(SettingsService::class)->set($key, $value);
    }

    public static function group(string $group): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('group', $group)->orderBy('key')->get();
    }
}
