<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'appointment_id', 'nurse_id',
        'visit_date', 'visit_time',
        'chief_complaint', 'assessment', 'diagnosis', 'treatment', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Includes soft-deleted (archived) patients so clinical history never
     * renders a null patient. Use $model->patient->trashed() to badge it.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withTrashed();
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id')->withDefault(['name' => 'Deleted user']);
    }

    public function dispensingRecords(): HasMany
    {
        return $this->hasMany(DispensingRecord::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('visit_date', $date);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('visit_date', today());
    }
}
