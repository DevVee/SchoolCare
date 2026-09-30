<?php

namespace App\Models;

use App\Services\LandingContent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An advisory shown on the public website, on the staff dashboard, or both,
 * between optional start and end times. Edited under Administration → Website.
 */
class Announcement extends Model
{
    public const TYPES = [
        'info'     => 'Information',
        'advisory' => 'Advisory',
        'urgent'   => 'Urgent',
    ];

    public const AUDIENCES = [
        'public' => 'Public website',
        'staff'  => 'Staff dashboard',
        'both'   => 'Website and staff dashboard',
    ];

    protected $fillable = [
        'title', 'body', 'type', 'link_label', 'link_url', 'audience',
        'starts_at', 'ends_at', 'is_enabled', 'sort_order', 'created_by',
    ];

    protected $attributes = [
        'type'       => 'info',
        'audience'   => 'public',
        'is_enabled' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at'  => 'datetime',
            'ends_at'    => 'datetime',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => LandingContent::forget());
        static::deleted(fn () => LandingContent::forget());
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /** Inside the start / end window at $at (default now). Empty bounds are open. */
    public function scopeActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at));
    }

    public function scopeForPublic(Builder $query): Builder
    {
        return $query->whereIn('audience', ['public', 'both']);
    }

    public function scopeForStaff(Builder $query): Builder
    {
        return $query->whereIn('audience', ['staff', 'both']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->is_enabled
            && ($this->starts_at === null || $this->starts_at->lte($at))
            && ($this->ends_at === null || $this->ends_at->gte($at));
    }

    /** Scheduled | Showing | Ended | Off (admin list). */
    public function windowStatus(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            ! $this->is_enabled                                   => 'off',
            $this->starts_at !== null && $this->starts_at->gt($at) => 'scheduled',
            $this->ends_at !== null && $this->ends_at->lt($at)     => 'ended',
            default                                               => 'showing',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function getAudienceLabelAttribute(): string
    {
        return self::AUDIENCES[$this->audience] ?? ucfirst((string) $this->audience);
    }

    /** x-ui tone for the type. */
    public function getToneAttribute(): string
    {
        return match ($this->type) {
            'urgent'   => 'danger',
            'advisory' => 'warning',
            default    => 'info',
        };
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'urgent'   => 'exclamation-octagon',
            'advisory' => 'exclamation-triangle',
            default    => 'info-circle',
        };
    }
}
