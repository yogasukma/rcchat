<?php

namespace App\Contracts;

interface AIServiceInterface
{
    /**
     * Generate a response from the AI service.
     */
    public function generateResponse(string $message): string;

    /**
     * Check if the AI service is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Get the name of the AI service provider.
     */
    public function getProviderName(): string;
}
