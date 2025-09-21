<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when brain dump processing fails
 */
class BrainDumpProcessingException extends Exception
{
    private string $errorType;
    private int $statusCode;

    public function __construct(string $message, string $errorType = 'processing_error', int $statusCode = 500, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
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
     * Create a new exception instance for AI service failures
     */
    public static function aiServiceError(string $message): self
    {
        return new static("Failed to process brain dump: {$message}");
    }

    /**
     * Create a new exception instance for invalid AI responses
     */
    public static function invalidAiResponse(string $details = ''): self
    {
        $message = 'Invalid response from AI service';
        if ($details) {
            $message .= ": {$details}";
        }
        return new static($message);
    }

    /**
     * Create a new exception instance for content validation failures
     */
    public static function invalidContent(string $reason): self
    {
        return new static("Invalid content for brain dump processing: {$reason}");
    }

    /**
     * Create a new exception instance for rate limiting
     */
    public static function rateLimitExceeded(int $retryAfter = 60): self
    {
        $exception = new static('Brain dump processing rate limit exceeded');
        $exception->retryAfter = $retryAfter;
        return $exception;
    }

    /**
     * Create a new exception instance for processing timeouts
     */
    public static function processingTimeout(int $timeoutSeconds): self
    {
        return new static("Brain dump processing timed out after {$timeoutSeconds} seconds");
    }

    /**
     * Retry after seconds for rate limiting
     */
    public int $retryAfter = 60;

    /**
     * Get the retry after value for rate limiting
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}