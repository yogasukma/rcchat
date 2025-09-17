<?php

namespace App\Http\Controllers;

use App\Contracts\AIServiceInterface;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected TokenService $tokenService;

    protected AIServiceInterface $aiService;

    public function __construct(TokenService $tokenService, AIServiceInterface $aiService)
    {
        $this->tokenService = $tokenService;
        $this->aiService = $aiService;
    }

    /**
     * Initialize chat session with app_key
     */
    public function init(Request $request): JsonResponse
    {
        $request->validate([
            'app_key' => 'required|string',
            'user_id' => 'nullable|string',
        ]);

        $roomId = Str::random(10);
        $appKey = $request->input('app_key');
        $userId = $request->input('user_id');

        // Create new chat session with token
        $session = $this->tokenService->createSession($roomId, $appKey, $userId);

        return response()->json([
            'status' => 'initialized',
            'roomId' => $session->room_id,
            'userToken' => $session->user_token,
            'userId' => $session->user_id,
        ], 201);
    }

    /**
     * Send a message and get AI response
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string',
        ]);

        // Get the chat session from middleware
        $session = $request->attributes->get('chat_session');

        info('Received message: '.$request->input('q').' for session '.json_encode($session));

        $question = $request->input('q');

        // Prepare context for AI service (including RunCloud token if available)
        $context = [];
        if ($session->app_key) {
            $context['runcloud_token'] = $session->app_key;
        }

        // Generate AI response using configured AI service with context
        $answer = $this->aiService->generateResponseWithContext($question, $context);
        $actions = [];

        // Create question message
        $session->messages()->create([
            'type' => 'question',
            'content' => $question,
        ]);

        // Create answer message
        $session->messages()->create([
            'type' => 'answer',
            'content' => $answer,
            'actions' => $actions,
        ]);
        
        // Update session activity and generate room name if needed
        $this->tokenService->updateSessionActivity($session);

        return response()->json([
            'a' => $answer,
            'actions' => $actions,
        ]);
    }

    /**
     * Get all chat messages from database
     */
    public function getChats(Request $request): JsonResponse
    {
        // Get the chat session from middleware
        $session = $request->attributes->get('chat_session');

        // Transform messages to match original format
        $msgs = $session->messages->map(function ($message) {
            if ($message->type === 'question') {
                return ['q' => $message->content];
            } else {
                return [
                    'a' => $message->content,
                    'actions' => $message->actions ?? [],
                ];
            }
        })->toArray();

        return response()->json([
            'roomId' => $session->room_id,
            'msgs' => $msgs,
            'app_key' => $session->app_key,
        ]);
    }

    /**
     * Clear chat session data
     */
    public function clearChats(Request $request): JsonResponse
    {
        // Get the chat session from middleware
        $session = $request->attributes->get('chat_session');

        // Delete all messages for this session
        $session->messages()->delete();

        return response()->json([
            'status' => 'cleared',
            'message' => 'Chat session cleared successfully.',
        ]);
    }

    /**
     * Get all rooms for a user
     */
    public function getUserRooms(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
        ]);

        $userId = $request->input('user_id');
        $rooms = $this->tokenService->getUserRooms($userId);

        // Transform rooms for frontend
        $roomsData = $rooms->map(function ($session) {
            $lastMessage = $session->messages->first();
            
            return [
                'roomId' => $session->room_id,
                'userToken' => $session->user_token,
                'roomName' => $session->room_name ?: 'New Chat',
                'lastActivity' => $session->last_activity?->toISOString(),
                'messageCount' => $session->messages()->count(),
                'lastMessage' => $lastMessage ? $lastMessage->content : null,
                'hasRunCloudToken' => !empty($session->app_key),
            ];
        });

        return response()->json([
            'userId' => $userId,
            'rooms' => $roomsData,
        ]);
    }
}
