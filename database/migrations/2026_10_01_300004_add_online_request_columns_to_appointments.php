<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online appointment requests (SSCMS web-new-appointment.php) and providers.
 *
 *  - patient_id becomes nullable: a public request that does not match a
 *    patient is stored unlinked; staff link it (or create the patient) before
 *    it can be approved.
 *  - requester_name / requester_contact / requester_email / requester_student_id:
 *    what the requester typed on the public form.
 *  - source: staff | online
 *  - provider: Doctor / Nurse / Dentist (settings appointment_providers), SSCMS appointee
 *  - specialist_visit_id: optional link to a scheduled specialist clinic day
 *
 * Runs outside a transaction so SQLite can rebuild the table for the nullable
 * change with foreign-key enforcement disabled.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'source')) {
                $table->string('source', 20)->default('staff')->after('status');
            }
            if (! Schema::hasColumn('appointments', 'requester_name')) {
                $table->string('requester_name', 150)->nullable()->after('source');
                $table->string('requester_contact', 30)->nullable()->after('requester_name');
                $table->string('requester_email', 150)->nullable()->after('requester_contact');
                $table->string('requester_student_id', 50)->nullable()->after('requester_email');
            }
            if (! Schema::hasColumn('appointments', 'provider')) {
                $table->string('provider', 60)->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('appointments', 'specialist_visit_id')) {
                $table->foreignId('specialist_visit_id')->nullable()->after('provider')
                      ->constrained('specialist_visits')->nullOnDelete();
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable()->change();
            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropConstrainedForeignId('specialist_visit_id');
            $table->dropColumn(['source', 'requester_name', 'requester_contact', 'requester_email', 'requester_student_id', 'provider']);
        });
        // patient_id stays nullable: unlinked online requests may exist.
    }
};
