<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DispensingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'consultation_id', 'patient_log_id', 'medicine_id',
        'quantity', 'dispensed_by', 'dispensed_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'dispensed_at' => 'datetime',
            'quantity'     => 'integer',
        ];
    }

    /**
     * Includes soft-deleted (archived) patients so clinical history never
     * renders a null patient. Use $model->patient->trashed() to badge it.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class)->withTrashed();
    }

    /** Clinic logbook visit this medicine was given during (nullable). */
    public function patientLog(): BelongsTo
    {
        return $this->belongsTo(PatientLog::class)->withTrashed();
    }

    /** Ledger rows for this dispense, one per batch consumed (FEFO). */
    public function transactions(): MorphMany
    {
        return $this->morphMany(InventoryTransaction::class, 'reference');
    }

    public function medicine(): BelongsTo
    {
        // Keep dispensing history readable after a medicine is removed.
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by')->withDefault(['name' => 'Deleted user']);
    }
}
