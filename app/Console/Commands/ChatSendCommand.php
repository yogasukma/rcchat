<?php

namespace App\Console\Commands;

use App\ChatSessionManager;
use Illuminate\Console\Command;

class ChatSendCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:send {message : The message to send to the chat} {--json : Output only JSON response}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a message to the chat and get AI response';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $message = $this->argument('message');
        
        $this->info("Sending message: {$message}");
        
        try {
            $data = ChatSessionManager::sendMessage($message);
            
            if ($this->option('json')) {
                // Output only JSON (same as /chats endpoint)
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                // Full formatted output with JSON
                $this->info('✅ Message sent successfully!');
                $this->newLine();
                
                // Output the JSON response (same as /chats endpoint)
                $this->line('<fg=green>JSON Response:</>');            
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                $this->newLine();
                
                // Also display formatted output for readability
                $this->line('<fg=cyan>AI Response:</>');                
                $this->line($data['a']);
                $this->newLine();
                $this->line('<fg=yellow>Available Actions:</>');                
                foreach ($data['actions'] as $key => $action) {
                    $this->line("  • {$key}: {$action}");
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
