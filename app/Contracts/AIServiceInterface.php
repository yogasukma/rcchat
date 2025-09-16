<?php

namespace App\Contracts;

interface AIServiceInterface
{
    /**
     * Generate a response from the AI service.
     */
    public function generateResponse(string $message): string;

    /**
     * Generate a response with additional context.
     */
    public function generateResponseWithContext(string $message, array $context = []): string;

    /**
     * Check if the AI service is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Get the name of the AI service provider.
     */
    public function getProviderName(): string;
}
