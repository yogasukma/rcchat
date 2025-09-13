<?php

namespace App\Console\Commands;

use App\Services\TokenService;
use Illuminate\Console\Command;

class PruneExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:prune {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired chat session tokens and their associated data';

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
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $expiredCount = \App\Models\ChatSession::expired()->count();
            $this->info("[DRY RUN] Would delete {$expiredCount} expired chat sessions.");

            return Command::SUCCESS;
        }

        $deletedCount = $this->tokenService->cleanupExpiredTokens();

        if ($deletedCount > 0) {
            $this->info("Successfully deleted {$deletedCount} expired chat sessions.");
        } else {
            $this->info('No expired chat sessions found.');
        }

        return Command::SUCCESS;
    }
}
