<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A disposed (written-off) batch: who, why, how many and its value. */
class MedicineDisposal extends Model
{
    protected $fillable = [
        'medicine_id', 'medicine_batch_id', 'batch_number', 'expiry_date',
        'quantity', 'unit_cost', 'total_cost', 'reason', 'disposed_by', 'disposed_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'disposed_at' => 'datetime',
            'quantity'    => 'integer',
            'unit_cost'   => 'decimal:2',
            'total_cost'  => 'decimal:2',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function disposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by')->withDefault(['name' => 'Deleted user']);
    }
}
