<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something the assistant proposed and the user may confirm (App\Services\Coco\CocoActions).
 *
 *   payload  what the model asked for, as resolved and checked by the server
 *            (never shown to the model again, never trusted without re-checking)
 *   preview  what the card shows: title, fields, recipients to pick from, the editable text
 *   result   what happened after Confirm: ['ok' => bool, 'text' => string, 'url' => ?string]
 */
class AiPendingAction extends Model
{
    use HasUuids;

    public const PENDING   = 'pending';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED   = 'expired';
    public const FAILED    = 'failed';

    protected $fillable = [
        'user_id', 'conversation_id', 'type', 'payload', 'preview',
        'status', 'expires_at', 'confirmed_at', 'result',
    ];

    protected $hidden = ['payload'];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'preview'      => 'array',
            'result'       => 'array',
            'expires_at'   => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Still pending but past its time: it can no longer be confirmed. */
    public function hasExpired(): bool
    {
        return $this->isPending() && $this->expires_at !== null && $this->expires_at->isPast();
    }
}
