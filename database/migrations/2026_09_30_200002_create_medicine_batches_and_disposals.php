<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch (lot) tracking, SSCMS medicine_batches + disposed_batches.
 *
 *  medicine_batches      one row per received lot. `quantity` is what is left.
 *                        medicines.quantity stays as the cached total of all
 *                        non-disposed batches; medicines.expiration_date caches
 *                        the earliest expiry among batches that still hold stock.
 *  medicine_disposals    disposal history (who, why, how many, value).
 *  inventory_transactions.batch_id   which lot a ledger row touched.
 *  inventory_transactions.transaction_type becomes a plain string so the new
 *                        'disposed' type (and future ones) fit; SQLite enforced
 *                        the old ENUM with a CHECK constraint.
 *  dispensing_records.patient_log_id links medicine given during a logbook visit.
 *  medicines.generic_name / barcode / purchase_price  fields lost from SSCMS.
 *
 * Runs outside a transaction so SQLite can rebuild altered tables with
 * foreign-key enforcement disabled (existing rows are copied as-is).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity')->default(0);          // remaining
            $table->unsignedInteger('initial_quantity')->default(0);  // received
            $table->date('received_at')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('supplier', 200)->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medicine_id', 'expiry_date']);
            $table->index(['expiry_date', 'quantity']);
        });

        Schema::create('medicine_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained('medicine_batches')->nullOnDelete();
            $table->string('batch_number', 100)->nullable();   // snapshot
            $table->date('expiry_date')->nullable();           // snapshot
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->text('reason');
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disposed_at');
            $table->timestamps();

            $table->index('disposed_at');
            $table->index(['medicine_id', 'disposed_at']);
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('transaction_type', 30)->change();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('medicine_id')
                  ->constrained('medicine_batches')->nullOnDelete();
        });

        Schema::table('dispensing_records', function (Blueprint $table) {
            $table->foreignId('patient_log_id')->nullable()->after('consultation_id')
                  ->constrained('patient_logs')->nullOnDelete();
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->string('generic_name', 200)->nullable()->after('name');
            $table->string('barcode', 100)->nullable()->unique()->after('generic_name');
            $table->decimal('purchase_price', 12, 2)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn(['generic_name', 'barcode', 'purchase_price']);
        });

        Schema::table('dispensing_records', function (Blueprint $table) {
            $table->dropForeign(['patient_log_id']);
            $table->dropColumn('patient_log_id');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
        // transaction_type stays a string: 'disposed' rows may exist.

        Schema::dropIfExists('medicine_disposals');
        Schema::dropIfExists('medicine_batches');
    }
};
