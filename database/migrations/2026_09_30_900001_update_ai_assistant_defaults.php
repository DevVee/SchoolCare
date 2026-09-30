<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI assistant defaults:
 * - Groq retired the Llama, Gemma and Mixtral models the assistant used to
 *   offer. A saved model that is no longer offered is replaced by the new
 *   default, so the settings page shows (and saves) the model actually used.
 *   The assistant also falls back at runtime (AiAssistantService::model).
 * - The assistant is now called Coco. The saved name changes only when it is
 *   still the old default "Cobi", so a custom name is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $updated = 0;

        $def = config('settings.groups.ai.fields.ai_model', []);
        $offered = array_keys($def['options'] ?? []);
        if ($offered !== [] && ! empty($def['default'])) {
            $updated += DB::table('settings')
                ->where('key', 'ai_model')
                ->whereNotIn('value', $offered)
                ->update(['value' => $def['default'], 'updated_at' => now()]);
        }

        $updated += DB::table('settings')
            ->where('key', 'ai_assistant_name')
            ->where('value', 'Cobi')
            ->update(['value' => 'Coco', 'updated_at' => now()]);

        if ($updated) {
            Cache::forget(SettingsService::CACHE_KEY);
        }
    }

    public function down(): void
    {
        // Not reversible: the old models no longer exist on Groq, and the new name is the default.
    }
};
