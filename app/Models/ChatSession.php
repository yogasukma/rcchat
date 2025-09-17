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
        'user_id',
        'room_name',
        'expires_at',
        'last_activity',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_activity' => 'datetime',
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

    /**
     * Scope to get sessions for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Update last activity timestamp.
     */
    public function updateLastActivity()
    {
        $this->last_activity = now();
        $this->save();
    }

    /**
     * Generate a room name from the first message.
     */
    public function generateRoomName()
    {
        $firstMessage = $this->messages()->where('type', 'question')->first();
        if ($firstMessage) {
            // Simple room name generation (can be enhanced with AI later)
            $content = str($firstMessage->content)->limit(30)->trim();
            $this->room_name = $content ?: 'New Chat';
            $this->save();
        }
    }
}
