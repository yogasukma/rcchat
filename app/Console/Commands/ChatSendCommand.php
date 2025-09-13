<?php

namespace App\Console\Commands;

use App\Contracts\AIServiceInterface;
use App\Services\TokenService;
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

    protected TokenService $tokenService;

    protected AIServiceInterface $aiService;

    public function __construct(TokenService $tokenService, AIServiceInterface $aiService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
        $this->aiService = $aiService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $message = $this->argument('message');

        if (! $this->option('json')) {
            $this->info("Sending message: {$message}");
        }

        try {
            // Get stored tokens
            $tokens = $this->getStoredTokens();
            if (! $tokens) {
                $this->error('❌ No active session found. Please run "chat:init" first.');

                return Command::FAILURE;
            }

            // Validate session
            $session = $this->tokenService->validateToken($tokens['userToken'], $tokens['roomId']);
            if (! $session) {
                $this->error('❌ Session expired or invalid. Please run "chat:init" again.');

                return Command::FAILURE;
            }

            // Generate AI response
            $answer = $this->aiService->generateResponse($message);
            $actions = [];

            // Store messages in database
            $session->messages()->create([
                'type' => 'question',
                'content' => $message,
            ]);

            $session->messages()->create([
                'type' => 'answer',
                'content' => $answer,
                'actions' => $actions,
            ]);

            $data = [
                'a' => $answer,
                'actions' => $actions,
            ];

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

                if (! empty($data['actions'])) {
                    $this->line('<fg=yellow>Available Actions:</>');
                    foreach ($data['actions'] as $key => $action) {
                        $this->line("  • {$key}: {$action}");
                    }
                } else {
                    $this->line('<fg=yellow>No actions available</>');
                }
            }

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
