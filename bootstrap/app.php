<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verify.chat.token' => \App\Http\Middleware\VerifyChatToken::class,
        ]);
        
        // Disable CSRF for API routes since this is now a pure API server
        $middleware->validateCsrfTokens(except: [
            '/init',
            '/chats',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
