<?php

use App\Contracts\AIServiceInterface;
use App\Services\GeminiService;
use App\Services\RunCloudMCPService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('can be instantiated with valid API key', function () {
    Config::set('services.gemini.api_key', 'test-api-key');

    $mcpService = $this->mock(RunCloudMCPService::class);
    $service = new GeminiService($mcpService);

    expect($service)->toBeInstanceOf(GeminiService::class);
    expect($service)->toBeInstanceOf(AIServiceInterface::class);
    expect($service->isConfigured())->toBeTrue();
    expect($service->getProviderName())->toBe('Gemini');
});

it('generates response from Gemini API successfully', function () {
    Config::set('services.gemini.api_key', 'test-api-key');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => 'This is a test response from Gemini',
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $mcpService = $this->mock(RunCloudMCPService::class);
    $mcpService->shouldReceive('isRunCloudRelated')->andReturn(false);

    $service = new GeminiService($mcpService);
    $response = $service->generateResponse('Hello, how are you?');

    expect($response)->toBe("I'm a RunCloud management assistant. I can help with servers, web applications, databases, and backups. Please ask RunCloud-related questions.");
});

it('returns fallback response when API fails', function () {
    Config::set('services.gemini.api_key', 'test-api-key');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);

    $mcpService = $this->mock(RunCloudMCPService::class);
    $mcpService->shouldReceive('isRunCloudRelated')->andReturn(false);

    $service = new GeminiService($mcpService);
    $response = $service->generateResponse('Hello');

    expect($response)->toBe("I'm a RunCloud management assistant. I can help with servers, web applications, databases, and backups. Please ask RunCloud-related questions.");
});

it('enhances RunCloud-related messages with context', function () {
    Config::set('services.gemini.api_key', 'test-api-key');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => 'Here are your RunCloud servers: server1, server2',
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $mcpService = $this->mock(RunCloudMCPService::class);
    $mcpService->shouldReceive('isConfigured')->andReturn(true);
    $mcpService->shouldReceive('isRunCloudRelated')->with('list my servers')->andReturn(true);
    $mcpService->shouldReceive('listServers')->with('rc_test_token')->andReturn([
        'content' => [
            [
                'type' => 'text',
                'text' => 'Server 1: production-server\nServer 2: staging-server',
            ],
        ],
    ]);
    $mcpService->shouldReceive('extractTextContent')->andReturn('Server 1: production-server\nServer 2: staging-server');

    $service = new GeminiService($mcpService);
    $response = $service->generateResponseWithContext('list my servers', ['runcloud_token' => 'rc_test_token']);

    expect($response)->toBe('Here are your RunCloud servers: server1, server2');
});
