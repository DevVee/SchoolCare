<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stops user / patient / medicine / category deletions from silently
 * destroying clinical history through ON DELETE CASCADE.
 *
 *  - consultations.nurse_id           cascade  → nullable + SET NULL
 *    (deleting a nurse account used to hard-delete all their consultations)
 *  - *.patient_id on appointments, consultations, dispensing_records,
 *    patient_logs                     cascade  → RESTRICT
 *    (patients are soft-deleted; a hard delete must never wipe records)
 *  - dispensing_records.medicine_id,
 *    inventory_transactions.medicine_id cascade → RESTRICT
 *    (ledger + dispensing history must survive a medicine hard delete)
 *  - medicines.category_id            cascade  → RESTRICT
 *    (deleting a category could hard-delete soft-deleted medicines)
 *
 * On SQLite, Laravel 11+ rebuilds each table with foreign keys disabled
 * during the copy, so existing rows (and rows referencing them) are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['nurse_id']);
            $table->dropForeign(['patient_id']);
            $table->unsignedBigInteger('nurse_id')->nullable()->change();
            $table->foreign('nurse_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
        });

        Schema::table('patient_logs', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
        });

        Schema::table('dispensing_records', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['medicine_id']);
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['medicine_id']);
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('medicine_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('medicine_categories')->cascadeOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['medicine_id']);
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
        });

        Schema::table('dispensing_records', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['medicine_id']);
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
        });

        Schema::table('patient_logs', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });

        // nurse_id stays nullable on rollback: rows whose nurse was deleted
        // hold NULL and cannot be made NOT NULL again without data loss.
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['nurse_id']);
            $table->dropForeign(['patient_id']);
            $table->foreign('nurse_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }
};
