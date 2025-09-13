<?php

namespace App\Services;

use App\Contracts\AIServiceInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements AIServiceInterface
{
    protected string $apiKey;

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');

        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured. Please set GEMINI_API_KEY in your environment.');
        }
    }

    /**
     * Generate a response using Gemini API.
     */
    public function generateResponse(string $message): string
    {
        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl.'/models/gemini-1.5-flash:generateContent?key='.$this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $message,
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1000,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->getFallbackResponse();
            }

            $data = $response->json();

            // Extract the generated text from Gemini response
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }

            Log::warning('Unexpected Gemini API response format', ['response' => $data]);

            return $this->getFallbackResponse();

        } catch (\Exception $e) {
            Log::error('Gemini API exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getFallbackResponse();
        }
    }

    /**
     * Get a fallback response when Gemini API fails.
     */
    protected function getFallbackResponse(): string
    {
        return "I apologize, but I'm having trouble generating a response right now. Please try again in a moment.";
    }

    /**
     * Check if the Gemini API is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Get the name of the AI service provider.
     */
    public function getProviderName(): string
    {
        return 'Gemini';
    }
}
