<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One received lot of a medicine. `quantity` is what is left on the shelf.
 *
 * Stock is consumed First-Expiry-First-Out (see InventoryService::consume):
 * earliest expiry first, batches without an expiry last, expired and
 * disposed batches never.
 */
class MedicineBatch extends Model
{
    protected $fillable = [
        'medicine_id', 'batch_number', 'expiry_date',
        'quantity', 'initial_quantity', 'received_at',
        'unit_cost', 'supplier', 'disposed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date'      => 'date',
            'received_at'      => 'date',
            'disposed_at'      => 'datetime',
            'quantity'         => 'integer',
            'initial_quantity' => 'integer',
            'unit_cost'        => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'batch_id');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(MedicineDisposal::class);
    }

    // ─── State ────────────────────────────────────────────────────────────────

    public function getIsDisposedAttribute(): bool
    {
        return $this->disposed_at !== null;
    }

    /** Expired = expiry date before today (usable through its printed date). */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->copy()->startOfDay()->lt(today());
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }
        $expiry = $this->expiry_date->copy()->startOfDay();

        return $expiry->gte(today()) && $expiry->lte(today()->addDays(Medicine::expiryWarningDays()));
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expiry_date === null
            ? null
            : (int) today()->diffInDays($this->expiry_date->copy()->startOfDay(), false);
    }

    /** Short status key: disposed | empty | expired | expiring | ok */
    public function getStatusAttribute(): string
    {
        return match (true) {
            $this->is_disposed      => 'disposed',
            $this->quantity <= 0    => 'empty',
            $this->is_expired       => 'expired',
            $this->is_expiring_soon => 'expiring',
            default                 => 'ok',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'disposed' => 'Disposed',
            'empty'    => 'Used up',
            'expired'  => 'Expired',
            'expiring' => 'Expiring soon',
            'ok'       => 'In stock',
        ][$this->status];
    }

    public function getStatusColorAttribute(): string
    {
        return [
            'disposed' => 'secondary',
            'empty'    => 'secondary',
            'expired'  => 'danger',
            'expiring' => 'warning',
            'ok'       => 'success',
        ][$this->status];
    }

    public function getLabelAttribute(): string
    {
        $parts = [$this->batch_number ?: 'No batch no.'];
        if ($this->expiry_date) {
            $parts[] = 'exp. '.$this->expiry_date->format('M d, Y');
        }

        return implode(', ', $parts);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Still counted in stock (not disposed). */
    public function scopeNotDisposed(Builder $query): Builder
    {
        return $query->whereNull('disposed_at');
    }

    /** Holding stock, not disposed. */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereNull('disposed_at')->where('quantity', '>', 0);
    }

    /** Can be dispensed / used: in stock and not expired. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->inStock()->where(fn ($q) => $q
            ->whereNull('expiry_date')
            ->orWhereDate('expiry_date', '>=', today()));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today());
    }

    /** First-Expiry-First-Out order: earliest expiry first, no-expiry last, then oldest received. */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                     ->orderBy('expiry_date')
                     ->orderBy('received_at')
                     ->orderBy('id');
    }
}
