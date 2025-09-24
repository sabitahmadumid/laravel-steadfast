<?php

namespace SabitAhmad\SteadFast\Exceptions;

use Exception;
use Throwable;

class SteadfastException extends Exception
{
    /**
     * Additional context about the error.
     */
    protected array $context = [];

    /**
     * List of known Steadfast API error codes.
     */
    public const ERROR_CODES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the exception context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create an exception for invalid configuration.
     *
     * @return static
     */
    public static function invalidConfig(string $missingKey): self
    {
        return new static(
            "Invalid Steadfast Courier configuration: Missing or invalid $missingKey",
            500,
            null,
            ['config_key' => $missingKey]
        );
    }

    /**
     * Create an exception for API errors.
     *
     * @return static
     */
    public static function apiError(string $message, array $response = []): self
    {
        $code = $response['status'] ?? 500;
        $errorMessage = self::ERROR_CODES[$code] ?? 'Unknown API Error';

        return new static(
            "Steadfast API Error ($code $errorMessage): $message",
            $code,
            null,
            ['api_response' => $response]
        );
    }

    /**
     * Create an exception for validation errors.
     *
     * @return static
     */
    public static function validationError(array $errors): self
    {
        $message = 'Validation failed: '.collect($errors)
            ->flatten()
            ->implode(', ');

        return new static(
            $message,
            422,
            null,
            ['validation_errors' => $errors]
        );
    }

    /**
     * Create an exception for bulk order processing errors.
     *
     * @return static
     */
    public static function bulkOrderError(string $message, array $context = []): self
    {
        return new static(
            "Bulk Order Error: $message",
            400,
            null,
            $context
        );
    }

    /**
     * Create an exception for authentication errors.
     *
     * @return static
     */
    public static function authenticationError(string $message = 'Invalid API credentials'): self
    {
        return new static(
            "Authentication Error: $message",
            401,
            null,
            ['auth_error' => true]
        );
    }

    /**
     * Create an exception for resource not found errors.
     *
     * @return static
     */
    public static function notFoundError(string $resource, string $identifier): self
    {
        return new static(
            "Resource not found: $resource with identifier '$identifier'",
            404,
            null,
            ['resource' => $resource, 'identifier' => $identifier]
        );
    }

    /**
     * Create an exception for service unavailable errors.
     *
     * @return static
     */
    public static function serviceUnavailable(string $message = 'Steadfast API is currently unavailable'): self
    {
        return new static(
            $message,
            503,
            null,
            ['service_unavailable' => true]
        );
    }

    /**
     * Create an exception for connection errors.
     *
     * @return static
     */
    public static function connectionError(Throwable $e): self
    {
        return new static(
            "Could not connect to Steadfast API: {$e->getMessage()}",
            $e->getCode(),
            $e,
            ['connection_error' => true]
        );
    }

    /**
     * Create an exception for rate limiting.
     *
     * @return static
     */
    public static function rateLimitExceeded(int $retryAfter = 60): self
    {
        return new static(
            "Steadfast API rate limit exceeded. Please try again in $retryAfter seconds.",
            429,
            null,
            ['retry_after' => $retryAfter]
        );
    }

    /**
     * Convert the exception to an array.
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->getContext(),
        ];
    }
}
