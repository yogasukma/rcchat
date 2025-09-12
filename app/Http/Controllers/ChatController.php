<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Initialize chat session with app_key
     */
    public function init(Request $request): JsonResponse
    {
        $request->validate([
            'app_key' => 'required|string',
        ]);

        $roomId = Str::random(10);
        
        session([
            'roomId' => $roomId,
            'msgs' => [],
            'app_key' => $request->input('app_key'),
        ]);

        return response()->json([
            'status' => 'initialized',
            'roomId' => $roomId,
        ]);
    }

    /**
     * Send a message and get AI response
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string',
        ]);

        // Check if session is initialized
        if (!session('roomId')) {
            return response()->json([
                'error' => 'Session not initialized. Please call /init first.',
            ], 400);
        }

        $question = $request->input('q');
        
        // Generate fake response using Faker
        $answer = fake()->sentence(rand(5, 15));
        $actions = [
            fake()->word() . '_' . fake()->word() => fake()->sentence(3),
            fake()->word() . '_' . fake()->word() => fake()->sentence(3),
        ];

        // Get current messages from session
        $msgs = session('msgs', []);
        
        // Add question and answer to messages
        $msgs[] = ['q' => $question];
        $msgs[] = ['a' => $answer, 'actions' => $actions];
        
        // Update session
        session(['msgs' => $msgs]);

        return response()->json([
            'a' => $answer,
            'actions' => $actions,
        ]);
    }

    /**
     * Get all chat messages from session
     */
    public function getChats(): JsonResponse
    {
        // Check if session is initialized
        if (!session('roomId')) {
            return response()->json([
                'error' => 'Session not initialized. Please call /init first.',
            ], 400);
        }

        return response()->json([
            'roomId' => session('roomId'),
            'msgs' => session('msgs', []),
            'app_key' => session('app_key'),
        ]);
    }

    /**
     * Clear chat session data
     */
    public function clearChats(): JsonResponse
    {
        // Check if session is initialized
        if (!session('roomId')) {
            return response()->json([
                'error' => 'Session not initialized. Nothing to clear.',
            ], 400);
        }

        // Clear all session data
        session()->forget(['roomId', 'msgs', 'app_key']);
        
        return response()->json([
            'status' => 'cleared',
            'message' => 'Chat session cleared successfully.',
        ]);
    }
}
