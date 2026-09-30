<?php

namespace App\Models;

use App\Support\DisplayFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scheduled doctor / dentist clinic day (SSCMS specialist_visits).
 */
class SpecialistVisit extends Model
{
    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'type', 'specialist_name', 'visit_date', 'start_time', 'end_time',
        'capacity', 'notes', 'status', 'created_by', 'updated_by',
    ];

    protected $attributes = [
        'status' => 'scheduled',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'capacity'   => 'integer',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('visit_date', '>=', today())->where('status', 'scheduled');
    }

    public static function types(): array
    {
        return settings()->list('specialist_types') ?: ['Doctor', 'Dentist'];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'primary',
            'completed' => 'success',
            'cancelled' => 'secondary',
            default     => 'light',
        };
    }

    public function getTimeRangeAttribute(): string
    {
        return DisplayFormat::timeRange($this->start_time, $this->end_time);
    }

    /** Appointments that hold a place in this visit (pending / approved). */
    public function bookedCount(): int
    {
        return $this->appointments()->whereIn('status', ['pending', 'approved'])->count();
    }

    public function remaining(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->bookedCount());
    }
}
