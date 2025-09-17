<?php

namespace App\Services;

use App\Models\ChatSession;
use Illuminate\Support\Str;

class TokenService
{
    /**
     * Token expiry time in hours
     */
    const TOKEN_EXPIRY_HOURS = 3;

    /**
     * Generate a secure user token for a room.
     */
    public function generateToken(): string
    {
        // Generate a cryptographically secure random token
        // Using 64 random characters gives us 384 bits of entropy
        return Str::random(64);
    }

    /**
     * Create a new chat session with token.
     */
    public function createSession(string $roomId, string $appKey, ?string $userId = null): ChatSession
    {
        $userToken = $this->generateToken();
        $expiresAt = now()->addHours(self::TOKEN_EXPIRY_HOURS);
        
        // Generate guest user ID if not provided
        if (!$userId) {
            $userId = 'guest_' . Str::random(8);
        }

        return ChatSession::create([
            'room_id' => $roomId,
            'user_token' => $userToken,
            'app_key' => $appKey,
            'user_id' => $userId,
            'expires_at' => $expiresAt,
            'last_activity' => now(),
        ]);
    }

    /**
     * Validate if a token is valid for a specific room.
     */
    public function validateToken(string $userToken, string $roomId): ?ChatSession
    {
        return ChatSession::where('user_token', $userToken)
            ->where('room_id', $roomId)
            ->valid()
            ->first();
    }

    /**
     * Check if a session exists and is valid.
     */
    public function isValidSession(string $userToken, string $roomId): bool
    {
        return $this->validateToken($userToken, $roomId) !== null;
    }

    /**
     * Get session by token and room ID.
     */
    public function getSession(string $userToken, string $roomId): ?ChatSession
    {
        return $this->validateToken($userToken, $roomId);
    }

    /**
     * Cleanup expired tokens (for scheduled commands).
     */
    public function cleanupExpiredTokens(): int
    {
        return ChatSession::expired()->delete();
    }

    /**
     * Extend session expiry by TOKEN_EXPIRY_HOURS.
     */
    public function extendSession(ChatSession $session): bool
    {
        $session->expires_at = now()->addHours(self::TOKEN_EXPIRY_HOURS);

        return $session->save();
    }

    /**
     * Revoke a session (set as expired).
     */
    public function revokeSession(ChatSession $session): bool
    {
        $session->expires_at = now()->subSecond();

        return $session->save();
    }

    /**
     * Get all rooms for a user, ordered by last activity.
     */
    public function getUserRooms(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return ChatSession::forUser($userId)
            ->valid()
            ->with(['messages' => function($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    /**
     * Update session activity and generate room name if needed.
     */
    public function updateSessionActivity(ChatSession $session): void
    {
        $session->updateLastActivity();
        
        // Generate room name if not set and has messages
        if (!$session->room_name && $session->messages()->count() > 0) {
            $session->generateRoomName();
        }
    }
}
