<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo attached to a clinic visit (e.g. injury documentation).
 * Stored on the private "local" disk; only served through
 * PatientLogAttachmentController::show after a permission check.
 */
class PatientLogAttachment extends Model
{
    protected $fillable = [
        'patient_log_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function booted(): void
    {
        // Remove the file with the row (row deletes are always explicit).
        static::deleted(function (PatientLogAttachment $a) {
            try {
                Storage::disk($a->disk)->delete($a->path);
            } catch (\Throwable) {
                // A missing file must never block the delete.
            }
        });
    }

    public function patientLog(): BelongsTo
    {
        return $this->belongsTo(PatientLog::class)->withTrashed();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withDefault(['name' => 'Deleted user']);
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format(max(1, $this->size / 1024)).' KB';
    }
}
