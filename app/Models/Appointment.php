<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'appointment_date', 'appointment_time',
        'purpose', 'status', 'approved_by', 'approved_at',
        'cancelled_reason', 'notes', 'created_by',
        // Online requests + provider (SSCMS parity)
        'source', 'requester_name', 'requester_contact', 'requester_email', 'requester_student_id',
        'provider', 'specialist_visit_id',
    ];

    public const SOURCE_STAFF  = 'staff';
    public const SOURCE_ONLINE = 'online';

    protected $attributes = [
        'source' => self::SOURCE_STAFF,
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'approved_at'      => 'datetime',
            'reminder_sent_at' => 'datetime',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function specialistVisit(): BelongsTo
    {
        return $this->belongsTo(SpecialistVisit::class);
    }

    // ─── Online requests ──────────────────────────────────────────────────────

    public function isOnlineRequest(): bool
    {
        return $this->source === self::SOURCE_ONLINE;
    }

    /** An online request that is not linked to a patient record yet. */
    public function needsPatientLink(): bool
    {
        return $this->exists && $this->patient_id === null;
    }

    /** Patient name, or the requester's name for an unlinked online request. */
    public function getDisplayNameAttribute(): string
    {
        return $this->patient?->full_name
            ?? ($this->requester_name ? $this->requester_name.' (not linked)' : 'Unknown patient');
    }

    public function scopeOnline($query)
    {
        return $query->where('source', self::SOURCE_ONLINE);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('appointment_date', $date);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('appointment_date', '>=', today())
                     ->where('status', 'approved');
    }

    // ─── State machine ────────────────────────────────────────────────────────

    /**
     * Allowed status transitions. completed / cancelled / no_show are terminal.
     */
    public const TRANSITIONS = [
        'pending'   => ['approved', 'cancelled'],
        'approved'  => ['completed', 'no_show', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show'   => [],
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return (self::TRANSITIONS[$this->status] ?? []) === [];
    }

    /** Only non-terminal appointments (pending / approved) may be edited or rescheduled. */
    public function isEditable(): bool
    {
        return ! $this->isTerminal();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isNoShow(): bool   { return $this->status === 'no_show'; }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'warning',
            'approved'  => 'success',
            'completed' => 'primary',
            'cancelled' => 'danger',
            'no_show'   => 'secondary',
            default     => 'light',
        };
    }

    public static function statuses(): array
    {
        return ['pending', 'approved', 'completed', 'cancelled', 'no_show'];
    }

    public static function statusLabels(): array
    {
        return [
            'pending'   => 'Pending',
            'approved'  => 'Approved',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show'   => 'No Show',
        ];
    }
}
