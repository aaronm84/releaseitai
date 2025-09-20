<?php

namespace App\Services\Ai\Contracts;

use App\Services\AiResponse;

interface AiProviderInterface
{
    /**
     * Complete text with the AI model
     */
    public function complete(string $prompt, array $options = []): AiResponse;

    /**
     * Get the provider name
     */
    public function getName(): string;

    /**
     * Check if the provider is available
     */
    public function isAvailable(): bool;

    /**
     * Get supported models for this provider
     */
    public function getSupportedModels(): array;

    /**
     * Get the default model for this provider
     */
    public function getDefaultModel(): string;
}