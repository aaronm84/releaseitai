<?php

namespace App\Exceptions;

use Exception;

class AiServiceException extends Exception
{
    protected $errorType;
    protected $provider;
    protected $retryAfter;
    protected $errorCode;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        ?string $errorType = null,
        ?string $provider = null,
        ?int $retryAfter = null,
        ?string $errorCode = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorType = $errorType;
        $this->provider = $provider;
        $this->retryAfter = $retryAfter;
        $this->errorCode = $errorCode;
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorType, [
            'rate_limit_exceeded',
            'service_unavailable',
            'timeout',
            'network_error'
        ]);
    }

    public function isRateLimited(): bool
    {
        return $this->errorType === 'rate_limit_exceeded';
    }

    public function isAuthenticationError(): bool
    {
        return $this->errorType === 'authentication_failed';
    }

    public function isQuotaExceeded(): bool
    {
        return $this->errorType === 'quota_exceeded';
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_type' => $this->errorType,
            'provider' => $this->provider,
            'retry_after' => $this->retryAfter,
            'error_code' => $this->errorCode,
            'retryable' => $this->isRetryable(),
        ];
    }
}