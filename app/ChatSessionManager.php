<?php

namespace App;

class ChatSessionManager
{
    private static string $sessionFile = 'storage/app/chat_session.json';

    public static function initSession(string $appKey): array
    {
        $roomId = \Illuminate\Support\Str::random(10);
        $sessionData = [
            'roomId' => $roomId,
            'msgs' => [],
            'app_key' => $appKey,
        ];
        
        self::saveSession($sessionData);
        
        return [
            'status' => 'initialized',
            'roomId' => $roomId,
        ];
    }

    public static function sendMessage(string $question): array
    {
        $sessionData = self::getSession();
        
        if (!$sessionData) {
            throw new \Exception('Session not initialized. Please call chat:init first.');
        }
        
        // Generate fake response
        $answer = fake()->sentence(rand(5, 15));
        $actions = [
            fake()->word() . '_' . fake()->word() => fake()->sentence(3),
            fake()->word() . '_' . fake()->word() => fake()->sentence(3),
        ];
        
        // Add to messages
        $sessionData['msgs'][] = ['q' => $question];
        $sessionData['msgs'][] = ['a' => $answer, 'actions' => $actions];
        
        self::saveSession($sessionData);
        
        return [
            'a' => $answer,
            'actions' => $actions,
        ];
    }

    public static function getChats(): array
    {
        $sessionData = self::getSession();
        
        if (!$sessionData) {
            throw new \Exception('Session not initialized. Please call chat:init first.');
        }
        
        return $sessionData;
    }

    private static function saveSession(array $data): void
    {
        $filePath = base_path(self::$sessionFile);
        
        // Create directory if it doesn't exist
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function getSession(): ?array
    {
        $filePath = base_path(self::$sessionFile);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        return json_decode($content, true);
    }


    public static function clearSession(): void
    {
        $filePath = base_path(self::$sessionFile);
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
