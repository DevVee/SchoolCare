<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSCMS patient fields that were lost in the rebuild, plus a school ID:
 *
 *  - student_id            school / employee ID (optional; used to detect duplicates on import
 *                          and to match online appointment requests)
 *  - other_contact         SSCMS other_contact (an extra number besides the patient's own)
 *  - guardian_facebook     SSCMS guardian_facebook
 *  - pediatrician_name     SSCMS pediatrician_name
 *  - pediatrician_contact  SSCMS pediatrician_contact
 *  - current_medications   collected by the public health information form
 *  - birthdate becomes nullable: SSCMS never stored a birthdate, so imported
 *    records have none. Age is shown as "not recorded" when it is missing.
 *
 * Runs outside a transaction so SQLite can rebuild the table for the nullable
 * change with foreign-key enforcement disabled (child rows are untouched).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'student_id')) {
                $table->string('student_id', 50)->nullable()->after('patient_number');
            }
            if (! Schema::hasColumn('patients', 'other_contact')) {
                $table->string('other_contact', 30)->nullable()->after('contact_number');
            }
            if (! Schema::hasColumn('patients', 'guardian_facebook')) {
                $table->string('guardian_facebook', 255)->nullable()->after('guardian_contact');
            }
            if (! Schema::hasColumn('patients', 'pediatrician_name')) {
                $table->string('pediatrician_name', 150)->nullable()->after('blood_type');
            }
            if (! Schema::hasColumn('patients', 'pediatrician_contact')) {
                $table->string('pediatrician_contact', 30)->nullable()->after('pediatrician_name');
            }
            if (! Schema::hasColumn('patients', 'current_medications')) {
                $table->text('current_medications')->nullable()->after('medical_conditions');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->date('birthdate')->nullable()->change();
            $table->index('student_id');
            $table->index(['category', 'year_level']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
            $table->dropIndex(['category', 'year_level']);
            $table->dropColumn(['student_id', 'other_contact', 'guardian_facebook', 'pediatrician_name', 'pediatrician_contact', 'current_medications']);
        });
        // birthdate stays nullable: imported rows may have no birthdate.
    }
};
