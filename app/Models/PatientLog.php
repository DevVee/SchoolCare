<?php

namespace App\Models;

use App\Observers\PatientLogObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(PatientLogObserver::class)]
class PatientLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'logged_by',
        'log_date', 'time_in', 'time_out',
        'severity', 'reasons', 'other_reason',
        'chief_complaint', 'vital_signs',
        'assessment', 'treatment',
        'disposition',
        'sms_guardian', 'sms_sent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date'     => 'date',
            'vital_signs'  => 'array',
            'reasons'      => 'array',
            'sms_guardian' => 'boolean',
            'sms_sent'     => 'boolean',
        ];
    }

    // ─── Disposition options ──────────────────────────────────────────────────

    /** [value => label], editable in Admin → Settings → Clinic (dispositions). */
    public static function dispositions(): array
    {
        return settings()->options('dispositions');
    }

    public static function dispositionIcons(): array
    {
        return [
            'rest_in_clinic'       => 'bi-hospital',
            'returned_to_class'    => 'bi-mortarboard',
            'sent_home'            => 'bi-house-heart',
            'referred_to_hospital' => 'bi-ambulance',
            'further_observation'  => 'bi-eye',
        ];
    }

    public static function dispositionColors(): array
    {
        return [
            'rest_in_clinic'       => 'info',
            'returned_to_class'    => 'success',
            'sent_home'            => 'warning',
            'referred_to_hospital' => 'danger',
            'further_observation'  => 'secondary',
        ];
    }

    // ─── Severity & reasons ───────────────────────────────────────────────────

    /** Severity choices, editable in Admin > Settings > Clinic (visit_severity_levels). */
    public static function severities(): array
    {
        return settings()->list('visit_severity_levels');
    }

    /** Reason choices, editable in Admin > Settings > Clinic (visit_reasons). */
    public static function reasonOptions(): array
    {
        return settings()->list('visit_reasons');
    }

    public const MAX_ATTACHMENTS = 5;

    public function getSeverityColorAttribute(): string
    {
        return match (mb_strtolower((string) $this->severity)) {
            'mild'     => 'success',
            'moderate' => 'warning',
            'severe'   => 'danger',
            default    => 'secondary',
        };
    }

    /** Structured reasons plus the "Other" text, in display order. */
    public function getReasonListAttribute(): array
    {
        $list = array_values(array_filter(array_map('trim', (array) ($this->reasons ?? [])), 'strlen'));
        if (filled($this->other_reason)) {
            $list[] = trim($this->other_reason);
        }

        return $list;
    }

    /**
     * One-line "why did they visit" text: structured reasons, else the free
     * text chief complaint (older entries only have the free text).
     */
    public function getComplaintSummaryAttribute(): string
    {
        $reasons = $this->reason_list;

        return $reasons !== [] ? implode(', ', $reasons) : trim((string) $this->chief_complaint);
    }

    public function getIsInClinicAttribute(): bool
    {
        return $this->time_out === null;
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getDispositionLabelAttribute(): string
    {
        return static::dispositions()[$this->disposition] ?? ucwords(str_replace('_', ' ', $this->disposition));
    }

    public function getDispositionColorAttribute(): string
    {
        return static::dispositionColors()[$this->disposition] ?? 'secondary';
    }

    public function getDispositionIconAttribute(): string
    {
        return static::dispositionIcons()[$this->disposition] ?? 'bi-check-circle';
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

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by')->withDefault(['name' => 'Deleted user']);
    }

    /** Medicines given during this visit (deducted from stock). */
    public function dispensingRecords(): HasMany
    {
        return $this->hasMany(DispensingRecord::class);
    }

    /** Photos attached to this visit (private disk). */
    public function attachments(): HasMany
    {
        return $this->hasMany(PatientLogAttachment::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** "Currently in clinic": today's visits with a time in and no time out. */
    public function scopeInClinic($query)
    {
        return $query->whereDate('log_date', today())
                     ->whereNotNull('time_in')
                     ->whereNull('time_out');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('log_date', today());
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('log_date', $date);
    }
}
