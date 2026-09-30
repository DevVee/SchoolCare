<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Clinic equipment and supplies (SSCMS asset inventory). */
class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'category', 'property_number', 'quantity', 'condition',
        'location', 'acquired_at', 'cost', 'image_path', 'notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'quantity'    => 'integer',
            'cost'        => 'decimal:2',
        ];
    }

    /** Condition choices, editable in Admin > Settings > Inventory (asset_conditions). */
    public static function conditions(): array
    {
        return settings()->list('asset_conditions');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault(['name' => 'Deleted user']);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function getConditionColorAttribute(): string
    {
        return match (mb_strtolower((string) $this->condition)) {
            'good', 'new'                  => 'success',
            'needs repair', 'old', 'fair' => 'warning',
            'damaged'                     => 'danger',
            default                       => 'secondary',
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($w) => $w
            ->where('name', 'like', "%{$term}%")
            ->orWhere('property_number', 'like', "%{$term}%")
            ->orWhere('location', 'like', "%{$term}%")
            ->orWhere('category', 'like', "%{$term}%"));
    }
}
