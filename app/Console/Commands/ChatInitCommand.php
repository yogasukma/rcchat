<?php

namespace App\Console\Commands;

use App\ChatSessionManager;
use Illuminate\Console\Command;

class ChatInitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:init {apikey : The API key for the chat session}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize a new chat session with the given API key';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apikey = $this->argument('apikey');
        
        $this->info("Initializing chat session with API key: {$apikey}");
        
        try {
            $data = ChatSessionManager::initSession($apikey);
            
            $this->info('✅ Chat session initialized successfully!');
            $this->line('Room ID: ' . $data['roomId']);
            $this->line('Status: ' . $data['status']);
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
