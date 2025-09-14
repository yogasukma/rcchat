<?php

namespace App\Console\Commands;

use App\Services\TokenService;
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
        if (! $this->option('json')) {
            $this->info('Retrieving chat history...');
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

            // Transform messages to match API format
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

            $data = [
                'roomId' => $session->room_id,
                'msgs' => $msgs,
                'app_key' => $session->app_key,
            ];

            if ($this->option('json')) {
                // Output raw JSON (same as /chats GET endpoint)
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                // Formatted display
                $this->displayFormattedHistory($data);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

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
        $this->line('  Total Messages: '.count($data['msgs']));
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

                if (isset($msg['actions']) && ! empty($msg['actions'])) {
                    $this->line('  <fg=yellow>Actions:</>');
                    foreach ($msg['actions'] as $key => $action) {
                        $this->line("    • {$key}: {$action}");
                    }
                }
            }
            $this->newLine();
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
