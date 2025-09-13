<?php

namespace App\Providers;

use App\Contracts\AIServiceInterface;
use App\Services\GeminiService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the AI service interface to a concrete implementation
        $this->app->bind(AIServiceInterface::class, GeminiService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
