<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $fillable = ['user_id', 'message', 'response', 'tokens_used'];

    /**
     * LOW-3 FIX: Hide message/response from accidental JSON serialization
     * (e.g. when the model is passed to API responses or logged).
     * These fields contain user queries that may include sensitive references.
     */
    protected $hidden = ['message', 'response'];

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Action cards the assistant proposed in this answer (App\Services\Coco\CocoActions). */
    public function actions(): HasMany
    {
        return $this->hasMany(AiPendingAction::class, 'conversation_id');
    }
}
