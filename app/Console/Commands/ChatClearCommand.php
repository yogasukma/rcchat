<?php

namespace App\Console\Commands;

use App\Services\TokenService;
use Illuminate\Console\Command;

class ChatClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the chat messages (but keep session active)';

    protected TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Clearing chat messages...');

        try {
            // Get stored tokens
            $tokens = $this->getStoredTokens();
            if (! $tokens) {
                $this->warn('⚠️ No active chat session found.');

                return Command::SUCCESS;
            }

            // Validate session
            $session = $this->tokenService->validateToken($tokens['userToken'], $tokens['roomId']);
            if (! $session) {
                $this->warn('⚠️ Session expired or invalid.');

                return Command::SUCCESS;
            }

            // Count messages before clearing
            $messageCount = $session->messages()->count();

            // Clear all messages (same as DELETE /chats endpoint)
            $session->messages()->delete();

            $this->info('✅ Chat messages cleared successfully!');
            $this->line('Room ID: '.$session->room_id.' (session still active)');
            $this->line('Messages cleared: '.$messageCount);
            $this->line('Session expires: '.$session->expires_at->format('Y-m-d H:i:s'));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Get stored tokens from file
     */
    private function getStoredTokens(): ?array
    {
        $filePath = storage_path('app/cli_tokens.json');
        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        return json_decode($content, true);
    }
}
