<?php

namespace OginiScoutDriver\Exceptions;

use Throwable;

/**
 * Exception thrown when connection to OginiSearch fails.
 */
class ConnectionException extends OginiException
{
    protected string $endpoint;
    protected int $timeout;

    /**
     * Create a new ConnectionException instance.
     *
     * @param string $message The exception message
     * @param string $endpoint The endpoint that failed
     * @param int $timeout The timeout that was used
     * @param Throwable|null $previous The previous exception
     * @param array|null $response The API response data
     */
    public function __construct(
        string $message = 'Connection to OginiSearch failed',
        string $endpoint = '',
        int $timeout = 0,
        ?Throwable $previous = null,
        ?array $response = null
    ) {
        parent::__construct($message, 0, $previous, $response, 'CONNECTION_FAILED');

        $this->endpoint = $endpoint;
        $this->timeout = $timeout;
    }

    /**
     * Get the endpoint that failed.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get the timeout that was used.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get detailed error information.
     *
     * @return array
     */
    public function getContext(): array
    {
        return array_merge(parent::toArray(), [
            'endpoint' => $this->endpoint,
            'timeout' => $this->timeout,
            'connection_error' => true,
        ]);
    }

    /**
     * Create a timeout exception.
     *
     * @param string $endpoint
     * @param int $timeout
     * @param Throwable|null $previous
     * @return static
     */
    public static function timeout(string $endpoint, int $timeout, ?Throwable $previous = null): static
    {
        return new static(
            "Connection to {$endpoint} timed out after {$timeout} seconds",
            $endpoint,
            $timeout,
            $previous
        );
    }

    /**
     * Create a refused connection exception.
     *
     * @param string $endpoint
     * @param Throwable|null $previous
     * @return static
     */
    public static function refused(string $endpoint, ?Throwable $previous = null): static
    {
        return new static(
            "Connection to {$endpoint} was refused",
            $endpoint,
            0,
            $previous
        );
    }

    /**
     * Create a DNS resolution exception.
     *
     * @param string $endpoint
     * @param Throwable|null $previous
     * @return static
     */
    public static function dnsResolution(string $endpoint, ?Throwable $previous = null): static
    {
        return new static(
            "Failed to resolve DNS for {$endpoint}",
            $endpoint,
            0,
            $previous
        );
    }

    /**
     * Create an SSL verification exception.
     *
     * @param string $endpoint
     * @param Throwable|null $previous
     * @return static
     */
    public static function sslVerification(string $endpoint, ?Throwable $previous = null): static
    {
        return new static(
            "SSL verification failed for {$endpoint}",
            $endpoint,
            0,
            $previous
        );
    }
}
