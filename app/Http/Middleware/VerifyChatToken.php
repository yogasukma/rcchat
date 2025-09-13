<?php

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyChatToken
{
    protected TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract token and room ID from various sources
        $userToken = $this->extractUserToken($request);
        $roomId = $this->extractRoomId($request);

        // Check if both token and room ID are provided
        if (! $userToken) {
            return response()->json([
                'error' => 'Missing user token. Please provide token via Authorization header, X-User-Token header, or userToken parameter.',
            ], 401);
        }

        if (! $roomId) {
            return response()->json([
                'error' => 'Missing room ID. Please provide room ID via X-Room-Id header or roomId parameter.',
            ], 401);
        }

        // Validate the session
        $session = $this->tokenService->validateToken($userToken, $roomId);

        if (! $session) {
            return response()->json([
                'error' => 'Invalid or expired session. Please initialize a new session.',
            ], 401);
        }

        // Add the session to the request for use in controllers
        $request->attributes->set('chat_session', $session);

        return $next($request);
    }

    /**
     * Extract user token from request.
     */
    private function extractUserToken(Request $request): ?string
    {
        // Try Authorization Bearer header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Try X-User-Token header
        $userTokenHeader = $request->header('X-User-Token');
        if ($userTokenHeader) {
            return $userTokenHeader;
        }

        // Try userToken from request body or query parameters
        return $request->input('userToken');
    }

    /**
     * Extract room ID from request.
     */
    private function extractRoomId(Request $request): ?string
    {
        // Try X-Room-Id header
        $roomIdHeader = $request->header('X-Room-Id');
        if ($roomIdHeader) {
            return $roomIdHeader;
        }

        // Try roomId from request body or query parameters
        return $request->input('roomId');
    }
}
