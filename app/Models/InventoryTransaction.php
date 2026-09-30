<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'medicine_id', 'batch_id', 'transaction_type', 'quantity',
        'before_quantity', 'after_quantity',
        'reference_id', 'reference_type',
        'batch_number', 'expiration_date', 'supplier',
        'notes', 'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date'  => 'date',
            'quantity'         => 'integer',
            'before_quantity'  => 'integer',
            'after_quantity'   => 'integer',
        ];
    }

    public function medicine(): BelongsTo
    {
        // Keep the ledger readable after a medicine is removed.
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getTypeBadgeAttribute(): string
    {
        return match ($this->transaction_type) {
            'stock_in'   => 'success',
            'stock_out'  => 'danger',
            'dispensed'  => 'warning',
            'adjustment' => 'info',
            'disposed'   => 'dark',
            default      => 'secondary',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            'stock_in'   => 'Stock In',
            'stock_out'  => 'Stock Out',
            'dispensed'  => 'Dispensed',
            'adjustment' => 'Adjustment',
            'disposed'   => 'Disposed',
            default      => ucfirst($this->transaction_type),
        };
    }
}
