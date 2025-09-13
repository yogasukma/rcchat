<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $fillable = [
        'room_id',
        'user_token',
        'app_key',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the messages for the chat session.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    /**
     * Check if the session has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the session is still valid.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Scope to get only valid (non-expired) sessions.
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired sessions.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
