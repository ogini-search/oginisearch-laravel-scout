<?php

namespace OginiScoutDriver\Exceptions;

use Throwable;

/**
 * Exception thrown when request validation fails.
 */
class ValidationException extends OginiException
{
    protected array $validationErrors;
    protected array $invalidData;

    /**
     * Create a new ValidationException instance.
     *
     * @param string $message The exception message
     * @param array $validationErrors The validation errors
     * @param array $invalidData The data that failed validation
     * @param Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message = 'Request validation failed',
        array $validationErrors = [],
        array $invalidData = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 422, $previous, null, 'VALIDATION_FAILED');

        $this->validationErrors = $validationErrors;
        $this->invalidData = $invalidData;
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get the invalid data.
     *
     * @return array
     */
    public function getInvalidData(): array
    {
        return $this->invalidData;
    }

    /**
     * Get detailed error information.
     *
     * @return array
     */
    public function getContext(): array
    {
        return array_merge(parent::toArray(), [
            'validation_errors' => $this->validationErrors,
            'invalid_data' => $this->invalidData,
            'validation_error' => true,
        ]);
    }

    /**
     * Create a validation exception for required fields.
     *
     * @param array $requiredFields
     * @param array $providedData
     * @return static
     */
    public static function missingRequired(array $requiredFields, array $providedData = []): static
    {
        $errors = [];
        foreach ($requiredFields as $field) {
            $errors[$field] = "The {$field} field is required.";
        }

        return new static(
            'Required fields are missing: ' . implode(', ', $requiredFields),
            $errors,
            $providedData
        );
    }

    /**
     * Create a validation exception for invalid field types.
     *
     * @param array $typeErrors
     * @param array $providedData
     * @return static
     */
    public static function invalidTypes(array $typeErrors, array $providedData = []): static
    {
        $errors = [];
        foreach ($typeErrors as $field => $expectedType) {
            $errors[$field] = "The {$field} field must be of type {$expectedType}.";
        }

        return new static(
            'Invalid field types provided',
            $errors,
            $providedData
        );
    }

    /**
     * Create a validation exception for invalid field values.
     *
     * @param array $valueErrors
     * @param array $providedData
     * @return static
     */
    public static function invalidValues(array $valueErrors, array $providedData = []): static
    {
        return new static(
            'Invalid field values provided',
            $valueErrors,
            $providedData
        );
    }

    /**
     * Create a validation exception for empty index name.
     *
     * @return static
     */
    public static function emptyIndexName(): static
    {
        return new static(
            'Index name cannot be empty',
            ['index_name' => 'The index name field is required and cannot be empty.'],
            []
        );
    }

    /**
     * Create a validation exception for invalid index name.
     *
     * @param string $indexName
     * @return static
     */
    public static function invalidIndexName(string $indexName): static
    {
        return new static(
            "Invalid index name: {$indexName}",
            ['index_name' => 'The index name contains invalid characters or format.'],
            ['index_name' => $indexName]
        );
    }

    /**
     * Create a validation exception for empty document.
     *
     * @return static
     */
    public static function emptyDocument(): static
    {
        return new static(
            'Document data cannot be empty',
            ['document' => 'The document field is required and cannot be empty.'],
            []
        );
    }

    /**
     * Create a validation exception for invalid query structure.
     *
     * @param array $query
     * @return static
     */
    public static function invalidQuery(array $query): static
    {
        return new static(
            'Invalid query structure provided',
            ['query' => 'The query structure is invalid or malformed.'],
            ['query' => $query]
        );
    }
}
