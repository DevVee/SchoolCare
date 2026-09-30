<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * patients.category / sex / blood_type were ENUM columns (CHECK constraints on
 * SQLite), so adding a choice in Admin → Settings → Clinic would make inserts
 * fail. They become plain strings; allowed values are enforced by validation
 * against settings (patient_categories, patient_genders, blood_types).
 * Existing values are kept as-is.
 *
 * Runs outside a transaction so Laravel can disable SQLite foreign-key
 * enforcement while it rebuilds the table (PRAGMA foreign_keys is ignored
 * inside a transaction) — child rows referencing patients are untouched.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('category', 60)->change();
            $table->string('sex', 30)->change();
            $table->string('blood_type', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Irreversible without data loss (new choices may exist); keep strings.
    }
};
