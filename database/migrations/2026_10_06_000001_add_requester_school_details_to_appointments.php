<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online appointment requests ask who the visit is for, like the SSCMS form's
 * Category, plus the grade, program and section the app already keeps for
 * patients (Settings > Academic). Staff see them on the request and they fill
 * in the new patient form when a patient is created from the request.
 *
 *  - requester_category:   a patient category value (Settings > Clinic)
 *  - requester_year_level / requester_program / requester_section
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'requester_category')) {
                $table->string('requester_category', 50)->nullable()->after('requester_student_id');
            }
            if (! Schema::hasColumn('appointments', 'requester_year_level')) {
                $table->string('requester_year_level', 50)->nullable()->after('requester_category');
            }
            if (! Schema::hasColumn('appointments', 'requester_program')) {
                $table->string('requester_program', 100)->nullable()->after('requester_year_level');
            }
            if (! Schema::hasColumn('appointments', 'requester_section')) {
                $table->string('requester_section', 50)->nullable()->after('requester_program');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['requester_category', 'requester_year_level', 'requester_program', 'requester_section']);
        });
    }
};
