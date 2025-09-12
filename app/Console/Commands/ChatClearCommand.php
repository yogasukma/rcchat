<?php

namespace App\Console\Commands;

use App\ChatSessionManager;
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
    protected $description = 'Clear the chat session and delete all chat history';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Clearing chat session...');
        
        try {
            // Check if session exists
            $sessionData = ChatSessionManager::getSession();
            
            if (!$sessionData) {
                $this->warn('⚠️ No active chat session found.');
                return Command::SUCCESS;
            }
            
            // Clear the session
            ChatSessionManager::clearSession();
            
            $this->info('✅ Chat session cleared successfully!');
            $this->line('Room ID: ' . $sessionData['roomId'] . ' (deleted)');
            $this->line('Messages cleared: ' . count($sessionData['msgs']));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
