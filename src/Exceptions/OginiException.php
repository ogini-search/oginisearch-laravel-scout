<?php

namespace OginiScoutDriver\Exceptions;

use Exception;
use Throwable;

class OginiException extends Exception
{
    protected ?array $response;
    protected ?string $errorCode;

    /**
     * Create a new OginiException instance.
     *
     * @param string $message The exception message
     * @param int $code The HTTP status code
     * @param Throwable|null $previous The previous exception
     * @param array|null $response The API response data
     * @param string|null $errorCode The API error code
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?array $response = null,
        ?string $errorCode = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->response = $response;
        $this->errorCode = $errorCode;
    }

    /**
     * Get the API response data.
     *
     * @return array|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * Get the API error code.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Check if this is a client error (4xx).
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    /**
     * Check if this is a server error (5xx).
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }

    /**
     * Check if this is a connection error.
     *
     * @return bool
     */
    public function isConnectionError(): bool
    {
        return $this->code === 0;
    }

    /**
     * Get a detailed error message.
     *
     * @return string
     */
    public function getDetailedMessage(): string
    {
        $message = $this->getMessage();

        if ($this->errorCode) {
            $message .= " (Error Code: {$this->errorCode})";
        }

        if ($this->code > 0) {
            $message .= " (HTTP Status: {$this->code})";
        }

        return $message;
    }

    /**
     * Get detailed error context.
     *
     * @return array
     */
    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_code' => $this->errorCode,
            'response' => $this->response,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Convert the exception to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_code' => $this->errorCode,
            'response' => $this->response,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Check if this is a retryable error.
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        // Server errors and timeouts are generally retryable
        return $this->isServerError() || $this->isConnectionError();
    }

    /**
     * Get suggested retry delay in seconds.
     *
     * @return int
     */
    public function getRetryDelay(): int
    {
        if ($this->isServerError()) {
            return 5; // 5 seconds for server errors
        }

        if ($this->isConnectionError()) {
            return 2; // 2 seconds for connection errors
        }

        return 0; // No retry for client errors
    }
}
