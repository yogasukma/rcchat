<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_session_id',
        'type',
        'content',
        'actions',
    ];

    protected $casts = [
        'actions' => 'array',
    ];

    /**
     * Get the chat session that owns the message.
     */
    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    /**
     * Check if this is a question message.
     */
    public function isQuestion(): bool
    {
        return $this->type === 'question';
    }

    /**
     * Check if this is an answer message.
     */
    public function isAnswer(): bool
    {
        return $this->type === 'answer';
    }

    /**
     * Scope to get only question messages.
     */
    public function scopeQuestions($query)
    {
        return $query->where('type', 'question');
    }

    /**
     * Scope to get only answer messages.
     */
    public function scopeAnswers($query)
    {
        return $query->where('type', 'answer');
    }
}
