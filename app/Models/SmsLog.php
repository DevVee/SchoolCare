<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsLog extends Model
{
    public const STATUSES = ['pending', 'sent', 'failed', 'skipped'];

    protected $fillable = [
        'recipient_number', 'recipient_name', 'message', 'event', 'status',
        'reference_id', 'reference_type',
        'api_response', 'provider_message_id', 'attempts',
        'sent_at', 'error_message', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'api_response' => 'array',
            'sent_at'      => 'datetime',
            'attempts'     => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'sent'    => 'success',
            'failed'  => 'danger',
            'pending' => 'warning',
            'skipped' => 'secondary',
            default   => 'secondary',
        };
    }

    public function getEventLabelAttribute(): string
    {
        if (! $this->event) {
            return 'Manual';
        }

        return config("settings.sms_events.{$this->event}.label")
            ?? ucwords(str_replace('_', ' ', $this->event));
    }
}
