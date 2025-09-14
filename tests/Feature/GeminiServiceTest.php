<?php

use App\Contracts\AIServiceInterface;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('can be instantiated with valid API key', function () {
    Config::set('services.gemini.api_key', 'test-api-key');

    $service = new GeminiService;
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

    $service = new GeminiService;
    $response = $service->generateResponse('Hello, how are you?');

    expect($response)->toBe('This is a test response from Gemini');
});

it('returns fallback response when API fails', function () {
    Config::set('services.gemini.api_key', 'test-api-key');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);

    $service = new GeminiService;
    $response = $service->generateResponse('Hello');

    expect($response)->toContain('having trouble generating a response');
});
