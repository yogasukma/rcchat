<?php

namespace App\Console\Commands;

use App\ChatSessionManager;
use Illuminate\Console\Command;

class ChatGetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:get {--json : Output raw JSON instead of formatted display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get chat history and display it';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Retrieving chat history...');
        
        try {
            $data = ChatSessionManager::getChats();
            
            if ($this->option('json')) {
                // Output raw JSON
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                // Formatted display
                $this->displayFormattedHistory($data);
            }
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Display chat history in a formatted way
     */
    private function displayFormattedHistory(array $data): void
    {
        $this->info('✅ Chat History Retrieved Successfully!');
        $this->newLine();
        
        $this->line('<fg=green>Session Info:</>');        
        $this->line("  Room ID: {$data['roomId']}");
        $this->line("  App Key: {$data['app_key']}");
        $this->line("  Total Messages: " . count($data['msgs']));
        $this->newLine();
        
        if (empty($data['msgs'])) {
            $this->line('<fg=yellow>No messages in chat history.</>');            
            return;
        }
        
        $this->line('<fg=cyan>Chat Messages:</>');        
        $this->newLine();
        
        foreach ($data['msgs'] as $index => $msg) {
            if (isset($msg['q'])) {
                // User question
                $this->line("<fg=blue>[➤ User]</> {$msg['q']}");
            } elseif (isset($msg['a'])) {
                // AI answer
                $this->line("<fg=magenta>[⚙️ AI]</> {$msg['a']}");
                
                if (isset($msg['actions']) && !empty($msg['actions'])) {
                    $this->line('  <fg=yellow>Actions:</>');                    
                    foreach ($msg['actions'] as $key => $action) {
                        $this->line("    • {$key}: {$action}");
                    }
                }
            }
            $this->newLine();
        }
    }
}
