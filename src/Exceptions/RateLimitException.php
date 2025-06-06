<?php

namespace OginiScoutDriver\Exceptions;

use Throwable;

/**
 * Exception thrown when rate limit is exceeded.
 */
class RateLimitException extends OginiException
{
    protected int $rateLimitRemaining;
    protected int $rateLimitReset;
    protected int $retryAfter;

    /**
     * Create a new RateLimitException instance.
     *
     * @param string $message The exception message
     * @param int $rateLimitRemaining Remaining requests in the current window
     * @param int $rateLimitReset When the rate limit resets (Unix timestamp)
     * @param int $retryAfter Seconds to wait before retrying
     * @param Throwable|null $previous The previous exception
     * @param array|null $response The API response data
     */
    public function __construct(
        string $message = 'Rate limit exceeded',
        int $rateLimitRemaining = 0,
        int $rateLimitReset = 0,
        int $retryAfter = 0,
        ?Throwable $previous = null,
        ?array $response = null
    ) {
        parent::__construct($message, 429, $previous, $response, 'RATE_LIMIT_EXCEEDED');

        $this->rateLimitRemaining = $rateLimitRemaining;
        $this->rateLimitReset = $rateLimitReset;
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get the remaining requests in the current rate limit window.
     *
     * @return int
     */
    public function getRateLimitRemaining(): int
    {
        return $this->rateLimitRemaining;
    }

    /**
     * Get when the rate limit resets (Unix timestamp).
     *
     * @return int
     */
    public function getRateLimitReset(): int
    {
        return $this->rateLimitReset;
    }

    /**
     * Get seconds to wait before retrying.
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get detailed error information.
     *
     * @return array
     */
    public function getContext(): array
    {
        return array_merge(parent::toArray(), [
            'rate_limit_remaining' => $this->rateLimitRemaining,
            'rate_limit_reset' => $this->rateLimitReset,
            'rate_limit_reset_time' => date('Y-m-d H:i:s', $this->rateLimitReset),
            'retry_after' => $this->retryAfter,
            'rate_limit_exceeded' => true,
        ]);
    }

    /**
     * Check if the rate limit has reset.
     *
     * @return bool
     */
    public function hasRateLimitReset(): bool
    {
        return time() >= $this->rateLimitReset;
    }

    /**
     * Get seconds until rate limit resets.
     *
     * @return int
     */
    public function getSecondsUntilReset(): int
    {
        return max(0, $this->rateLimitReset - time());
    }

    /**
     * Create a rate limit exception from response headers.
     *
     * @param array $headers
     * @param Throwable|null $previous
     * @return static
     */
    public static function fromHeaders(array $headers, ?Throwable $previous = null): static
    {
        $remaining = (int) ($headers['X-RateLimit-Remaining'] ?? 0);
        $reset = (int) ($headers['X-RateLimit-Reset'] ?? time() + 3600);
        $retryAfter = (int) ($headers['Retry-After'] ?? 60);

        return new static(
            'API rate limit exceeded. Try again later.',
            $remaining,
            $reset,
            $retryAfter,
            $previous
        );
    }

    /**
     * Create a search rate limit exception.
     *
     * @param int $retryAfter
     * @param Throwable|null $previous
     * @return static
     */
    public static function forSearch(int $retryAfter = 60, ?Throwable $previous = null): static
    {
        return new static(
            'Search request rate limit exceeded. Too many search requests.',
            0,
            time() + $retryAfter,
            $retryAfter,
            $previous
        );
    }

    /**
     * Create an indexing rate limit exception.
     *
     * @param int $retryAfter
     * @param Throwable|null $previous
     * @return static
     */
    public static function forIndexing(int $retryAfter = 60, ?Throwable $previous = null): static
    {
        return new static(
            'Indexing request rate limit exceeded. Too many indexing operations.',
            0,
            time() + $retryAfter,
            $retryAfter,
            $previous
        );
    }
}
