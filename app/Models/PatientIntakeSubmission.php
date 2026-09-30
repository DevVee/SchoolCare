<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public Student Health Information Form submission waiting for review.
 * `payload` holds the submitted patient fields; nothing reaches `patients`
 * until staff approve it.
 */
class PatientIntakeSubmission extends Model
{
    public const STATUSES = [
        'pending'  => 'Pending review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'payload', 'student_id', 'last_name', 'first_name', 'birthdate', 'contact',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
        'matched_patient_id', 'patient_id', 'submitted_ip', 'consent_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'birthdate'   => 'date',
            'reviewed_at' => 'datetime',
            'consent_at'  => 'datetime',
        ];
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function matchedPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'matched_patient_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getFullNameAttribute(): string
    {
        $p = $this->payload ?? [];

        return trim(implode(' ', array_filter([
            $p['first_name'] ?? $this->first_name,
            ! empty($p['middle_name']) ? mb_substr($p['middle_name'], 0, 1).'.' : null,
            $p['last_name'] ?? $this->last_name,
            $p['suffix'] ?? null,
        ])));
    }

    public function getStatusBadgeAttribute(): string
    {
        return ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'secondary'][$this->status] ?? 'light';
    }

    /**
     * Existing patients that look like the submitter: same student ID, or the
     * same first + last name and birthdate.
     */
    public function possibleMatches()
    {
        $p = $this->payload ?? [];

        return Patient::query()
            ->where(function ($q) use ($p) {
                $sid = trim((string) ($p['student_id'] ?? ''));
                if ($sid !== '') {
                    $q->orWhere('student_id', $sid);
                }
                if (! empty($p['first_name']) && ! empty($p['last_name'])) {
                    $q->orWhere(function ($w) use ($p) {
                        $w->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($p['first_name']))])
                          ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($p['last_name']))]);
                        if (! empty($p['birthdate'])) {
                            $w->where(fn ($b) => $b->whereDate('birthdate', $p['birthdate'])->orWhereNull('birthdate'));
                        }
                    });
                }
            })
            ->orderBy('last_name')
            ->limit(5)
            ->get();
    }
}
