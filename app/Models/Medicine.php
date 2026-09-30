<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'generic_name', 'barcode', 'category_id', 'description',
        'quantity', 'unit', 'purchase_price', 'expiration_date',
        'batch_number', 'supplier',
        'low_stock_threshold', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date'    => 'date',
            'is_active'          => 'boolean',
            'quantity'           => 'integer',
            'low_stock_threshold'=> 'integer',
            'purchase_price'     => 'decimal:2',
        ];
    }

    /**
     * Stock that exists without a batch (a medicine created with a quantity
     * outside the stock-in flow, e.g. imports or factories) gets an opening
     * batch so medicines.quantity always equals the sum of its batches.
     * medicines.expiration_date / batch_number are caches of the batches
     * (see InventoryService::refreshCache).
     */
    protected static function booted(): void
    {
        static::created(function (Medicine $medicine) {
            if ((int) $medicine->quantity > 0 && ! $medicine->batches()->exists()) {
                $medicine->batches()->create([
                    'batch_number'     => $medicine->batch_number,
                    'expiry_date'      => $medicine->expiration_date,
                    'quantity'         => (int) $medicine->quantity,
                    'initial_quantity' => (int) $medicine->quantity,
                    'received_at'      => today(),
                    'unit_cost'        => $medicine->purchase_price,
                    'supplier'         => $medicine->supplier,
                    'created_by'       => auth()->id(),
                ]);
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(MedicineDisposal::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MedicineCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function dispensingRecords(): HasMany
    {
        return $this->hasMany(DispensingRecord::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    /**
     * Number of days ahead that counts as "expiring soon".
     * Single source of truth for the warning window — Admin → Settings →
     * Inventory (`expiry_warning_days`).
     */
    public static function expiryWarningDays(): int
    {
        return max(1, (int) settings('expiry_warning_days', 30));
    }

    /**
     * Expired = the expiry date is before today (a medicine is still usable
     * on its printed expiry date). Matches scopeExpired().
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiration_date !== null
            && $this->expiration_date->copy()->startOfDay()->lt(today());
    }

    /**
     * Expiring soon = not yet expired and expires within the warning window
     * (today … today + N days, inclusive). Matches scopeExpiringSoon().
     *
     * Note: Carbon 3's diffInDays() is signed, so this uses plain date
     * comparisons instead of a diff to avoid flagging every future date.
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        if ($this->expiration_date === null) {
            return false;
        }

        $expiry = $this->expiration_date->copy()->startOfDay();

        return $expiry->gte(today())
            && $expiry->lte(today()->addDays(static::expiryWarningDays()));
    }

    /** Days until expiry (negative when already expired), or null. */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expiration_date === null
            ? null
            : (int) today()->diffInDays($this->expiration_date->copy()->startOfDay(), false);
    }

    /**
     * Whether this medicine may be dispensed at all (quantity checked separately).
     */
    public function isDispensable(): bool
    {
        return $this->is_active && ! $this->trashed() && $this->availableQuantity() > 0;
    }

    /**
     * Units that can be dispensed right now: stock in unexpired, undisposed
     * batches (FEFO pool). Stock not yet covered by any batch (legacy rows)
     * counts when the medicine's own expiry has not passed. Never more than
     * the cached total.
     */
    public function availableQuantity(): int
    {
        $usable  = (int) $this->batches()->usable()->sum('quantity');
        $batched = (int) $this->batches()->notDisposed()->sum('quantity');

        $unbatched = max(0, (int) $this->quantity - $batched);
        if ($unbatched > 0 && ! $this->is_expired) {
            $usable += $unbatched;
        }

        return max(0, min($usable, (int) $this->quantity));
    }

    /** Units held in expired, undisposed batches (waiting for disposal). */
    public function expiredQuantity(): int
    {
        return (int) $this->batches()->inStock()->expired()->sum('quantity');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'low_stock_threshold');
    }

    public function scopeExpiringSoon($query, ?int $days = null)
    {
        $days ??= static::expiryWarningDays();

        return $query->whereNotNull('expiration_date')
                     ->whereDate('expiration_date', '>=', today())
                     ->whereDate('expiration_date', '<=', today()->addDays($days));
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiration_date')
                     ->whereDate('expiration_date', '<', today());
    }

    public function scopeNotExpired($query)
    {
        return $query->where(fn ($q) => $q->whereNull('expiration_date')
                                          ->orWhereDate('expiration_date', '>=', today()));
    }

    /**
     * Active medicines with usable stock: at least one unexpired, undisposed
     * batch holding stock (legacy medicines without batches fall back to the
     * medicine's own expiry). The only ones that can be dispensed.
     */
    public function scopeDispensable($query)
    {
        return $query->active()
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q
                ->whereHas('batches', fn ($b) => $b->usable())
                ->orWhere(fn ($q2) => $q2->whereDoesntHave('batches')->notExpired()));
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($w) => $w
            ->where('name', 'like', "%{$term}%")
            ->orWhere('generic_name', 'like', "%{$term}%")
            ->orWhere('barcode', $term)
            ->orWhere('batch_number', 'like', "%{$term}%")
            ->orWhere('supplier', 'like', "%{$term}%"));
    }
}
