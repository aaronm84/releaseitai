<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when content processing fails
 */
class ContentProcessingException extends Exception
{
    private string $errorType;
    private int $statusCode;

    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, string $errorType = 'processing_error', int $statusCode = 500)
    {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
        $this->statusCode = $statusCode;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Create a new exception for entity matching failures
     */
    public static function entityMatchingFailed(string $details): self
    {
        return new static("Entity matching failed: {$details}", 0, null, 'entity_matching_error', 422);
    }

    /**
     * Create a new exception for AI service failures
     */
    public static function aiServiceError(string $message): self
    {
        return new static("AI service error: {$message}", 0, null, 'ai_service_error', 503);
    }

    /**
     * Create a new exception for validation failures
     */
    public static function validationFailed(string $reason): self
    {
        return new static("Content validation failed: {$reason}", 0, null, 'validation_error', 422);
    }

    /**
     * Create a new exception for unsupported content types
     */
    public static function unsupportedContentType(string $type): self
    {
        return new static("Unsupported content type: {$type}", 0, null, 'unsupported_type', 422);
    }
}