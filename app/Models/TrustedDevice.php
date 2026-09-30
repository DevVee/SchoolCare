<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser that skips the email sign-in code until expires_at
 * (App\Services\SignInCodes). token_hash is sha256 of the cookie token.
 */
class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id', 'token_hash', 'user_agent', 'ip_address', 'expires_at', 'last_used_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Not yet expired. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
