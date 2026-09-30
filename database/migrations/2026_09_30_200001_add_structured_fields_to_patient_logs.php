<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured clinic visits (SSCMS visits.severity + visit_reasons).
 *
 *  - severity      one of settings('visit_severity_levels'), e.g. Mild / Moderate / Severe
 *  - reasons       JSON list of reasons picked from settings('visit_reasons')
 *  - other_reason  free text for "Other"
 *  - chief_complaint becomes optional free-text notes. Existing values are kept.
 *
 * Runs outside a transaction so SQLite can rebuild the table for the
 * nullable change with foreign-key enforcement disabled.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('patient_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_logs', 'severity')) {
                $table->string('severity', 40)->nullable()->after('time_out');
            }
            if (! Schema::hasColumn('patient_logs', 'reasons')) {
                $table->json('reasons')->nullable()->after('severity');
            }
            if (! Schema::hasColumn('patient_logs', 'other_reason')) {
                $table->string('other_reason', 255)->nullable()->after('reasons');
            }
        });

        Schema::table('patient_logs', function (Blueprint $table) {
            $table->text('chief_complaint')->nullable()->change();
            $table->index(['log_date', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::table('patient_logs', function (Blueprint $table) {
            $table->dropIndex(['log_date', 'severity']);
            $table->dropColumn(['severity', 'reasons', 'other_reason']);
        });
        // chief_complaint stays nullable: new rows may have no free text.
    }
};
