<?php

namespace App\Console\Commands;

use App\Services\TokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
        $apikey = $this->argument('apikey');

        $this->info("Initializing chat session with API key: {$apikey}");

        try {
            // Clear any existing session
            $this->clearStoredTokens();

            // Generate room ID and create session
            $roomId = Str::random(10);
            $session = $this->tokenService->createSession($roomId, $apikey);

            // Store tokens locally for CLI commands
            $this->storeTokens($session->room_id, $session->user_token);

            $this->info('✅ Chat session initialized successfully!');
            $this->line('Room ID: '.$session->room_id);
            $this->line('User Token: '.$session->user_token);
            $this->line('Expires: '.$session->expires_at->format('Y-m-d H:i:s'));
            $this->line('Status: initialized');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Store tokens in local file for CLI commands
     */
    private function storeTokens(string $roomId, string $userToken): void
    {
        $tokenData = [
            'roomId' => $roomId,
            'userToken' => $userToken,
            'created_at' => now()->toISOString(),
        ];

        $filePath = storage_path('app/cli_tokens.json');
        file_put_contents($filePath, json_encode($tokenData, JSON_PRETTY_PRINT));
    }

    /**
     * Clear stored tokens
     */
    private function clearStoredTokens(): void
    {
        $filePath = storage_path('app/cli_tokens.json');
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
