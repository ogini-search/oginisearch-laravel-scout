<?php

namespace OginiScoutDriver\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Exceptions\OginiException;
use OginiScoutDriver\Exceptions\ConnectionException;
use OginiScoutDriver\Exceptions\ValidationException;
use OginiScoutDriver\Exceptions\IndexNotFoundException;
use OginiScoutDriver\Exceptions\RateLimitException;
use OginiScoutDriver\Exceptions\ErrorCodes;

class ExceptionHandlingTest extends TestCase
{
    /** @test */
    public function test_ogini_exception_basic_functionality()
    {
        $exception = new OginiException('Test message', 500, null, ['key' => 'value'], 'TEST_ERROR');

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals('TEST_ERROR', $exception->getErrorCode());
        $this->assertEquals(['key' => 'value'], $exception->getResponse());
        $this->assertTrue($exception->isServerError());
        $this->assertFalse($exception->isClientError());
        $this->assertFalse($exception->isConnectionError());
    }

    /** @test */
    public function test_ogini_exception_error_categorization()
    {
        // Client error
        $clientException = new OginiException('Client error', 400);
        $this->assertTrue($clientException->isClientError());
        $this->assertFalse($clientException->isServerError());

        // Server error
        $serverException = new OginiException('Server error', 500);
        $this->assertTrue($serverException->isServerError());
        $this->assertFalse($serverException->isClientError());

        // Connection error
        $connectionException = new OginiException('Connection error', 0);
        $this->assertTrue($connectionException->isConnectionError());
        $this->assertFalse($connectionException->isClientError());
        $this->assertFalse($connectionException->isServerError());
    }

    /** @test */
    public function test_ogini_exception_retry_logic()
    {
        $serverException = new OginiException('Server error', 500);
        $this->assertTrue($serverException->isRetryable());
        $this->assertEquals(5, $serverException->getRetryDelay());

        $connectionException = new OginiException('Connection error', 0);
        $this->assertTrue($connectionException->isRetryable());
        $this->assertEquals(2, $connectionException->getRetryDelay());

        $clientException = new OginiException('Client error', 400);
        $this->assertFalse($clientException->isRetryable());
        $this->assertEquals(0, $clientException->getRetryDelay());
    }

    /** @test */
    public function test_ogini_exception_detailed_message()
    {
        $exception = new OginiException('Test message', 500, null, null, 'TEST_CODE');
        $detailedMessage = $exception->getDetailedMessage();

        $this->assertStringContainsString('Test message', $detailedMessage);
        $this->assertStringContainsString('Error Code: TEST_CODE', $detailedMessage);
        $this->assertStringContainsString('HTTP Status: 500', $detailedMessage);
    }

    /** @test */
    public function test_ogini_exception_context()
    {
        $exception = new OginiException('Test message', 500, null, ['response' => 'data'], 'TEST_CODE');
        $context = $exception->getContext();

        $this->assertArrayHasKey('message', $context);
        $this->assertArrayHasKey('code', $context);
        $this->assertArrayHasKey('error_code', $context);
        $this->assertArrayHasKey('response', $context);
        $this->assertArrayHasKey('file', $context);
        $this->assertArrayHasKey('line', $context);
        $this->assertArrayHasKey('trace', $context);

        $this->assertEquals('Test message', $context['message']);
        $this->assertEquals(500, $context['code']);
        $this->assertEquals('TEST_CODE', $context['error_code']);
        $this->assertEquals(['response' => 'data'], $context['response']);
    }

    /** @test */
    public function test_connection_exception_creation()
    {
        $exception = new ConnectionException(
            'Connection failed',
            'https://api.example.com',
            30
        );

        $this->assertEquals('Connection failed', $exception->getMessage());
        $this->assertEquals('https://api.example.com', $exception->getEndpoint());
        $this->assertEquals(30, $exception->getTimeout());
        $this->assertEquals(0, $exception->getCode());
        $this->assertEquals('CONNECTION_FAILED', $exception->getErrorCode());
    }

    /** @test */
    public function test_connection_exception_static_methods()
    {
        $timeoutException = ConnectionException::timeout('https://api.example.com', 30);
        $this->assertStringContainsString('timed out after 30 seconds', $timeoutException->getMessage());
        $this->assertEquals('https://api.example.com', $timeoutException->getEndpoint());
        $this->assertEquals(30, $timeoutException->getTimeout());

        $refusedException = ConnectionException::refused('https://api.example.com');
        $this->assertStringContainsString('was refused', $refusedException->getMessage());

        $dnsException = ConnectionException::dnsResolution('api.example.com');
        $this->assertStringContainsString('Failed to resolve DNS', $dnsException->getMessage());

        $sslException = ConnectionException::sslVerification('https://api.example.com');
        $this->assertStringContainsString('SSL verification failed', $sslException->getMessage());
    }

    /** @test */
    public function test_connection_exception_context()
    {
        $exception = new ConnectionException('Test', 'https://api.example.com', 30);
        $context = $exception->getContext();

        $this->assertArrayHasKey('endpoint', $context);
        $this->assertArrayHasKey('timeout', $context);
        $this->assertArrayHasKey('connection_error', $context);
        $this->assertEquals('https://api.example.com', $context['endpoint']);
        $this->assertEquals(30, $context['timeout']);
        $this->assertTrue($context['connection_error']);
    }

    /** @test */
    public function test_validation_exception_creation()
    {
        $validationErrors = ['name' => 'Required field missing'];
        $invalidData = ['name' => ''];

        $exception = new ValidationException(
            'Validation failed',
            $validationErrors,
            $invalidData
        );

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals($validationErrors, $exception->getValidationErrors());
        $this->assertEquals($invalidData, $exception->getInvalidData());
        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals('VALIDATION_FAILED', $exception->getErrorCode());
    }

    /** @test */
    public function test_validation_exception_static_methods()
    {
        $missingRequiredException = ValidationException::missingRequired(['name', 'email']);
        $this->assertStringContainsString('Required fields are missing', $missingRequiredException->getMessage());
        $validationErrors = $missingRequiredException->getValidationErrors();
        $this->assertArrayHasKey('name', $validationErrors);
        $this->assertArrayHasKey('email', $validationErrors);

        $typeException = ValidationException::invalidTypes(['age' => 'integer']);
        $this->assertStringContainsString('Invalid field types', $typeException->getMessage());

        $emptyIndexException = ValidationException::emptyIndexName();
        $this->assertStringContainsString('Index name cannot be empty', $emptyIndexException->getMessage());

        $invalidIndexException = ValidationException::invalidIndexName('invalid@name');
        $this->assertStringContainsString('Invalid index name', $invalidIndexException->getMessage());

        $emptyDocException = ValidationException::emptyDocument();
        $this->assertStringContainsString('Document data cannot be empty', $emptyDocException->getMessage());

        $invalidQueryException = ValidationException::invalidQuery(['invalid' => 'query']);
        $this->assertStringContainsString('Invalid query structure', $invalidQueryException->getMessage());
    }

    /** @test */
    public function test_validation_exception_context()
    {
        $validationErrors = ['name' => 'Required'];
        $invalidData = ['name' => ''];

        $exception = new ValidationException('Test', $validationErrors, $invalidData);
        $context = $exception->getContext();

        $this->assertArrayHasKey('validation_errors', $context);
        $this->assertArrayHasKey('invalid_data', $context);
        $this->assertArrayHasKey('validation_error', $context);
        $this->assertEquals($validationErrors, $context['validation_errors']);
        $this->assertEquals($invalidData, $context['invalid_data']);
        $this->assertTrue($context['validation_error']);
    }

    /** @test */
    public function test_index_not_found_exception_creation()
    {
        $exception = new IndexNotFoundException('test_index');

        $this->assertStringContainsString('test_index', $exception->getMessage());
        $this->assertEquals('test_index', $exception->getIndexName());
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals('INDEX_NOT_FOUND', $exception->getErrorCode());
    }

    /** @test */
    public function test_index_not_found_exception_static_methods()
    {
        $searchException = IndexNotFoundException::forSearch('test_index');
        $this->assertStringContainsString('Cannot search in index', $searchException->getMessage());

        $indexingException = IndexNotFoundException::forIndexing('test_index');
        $this->assertStringContainsString('Cannot index documents', $indexingException->getMessage());

        $deletionException = IndexNotFoundException::forDeletion('test_index');
        $this->assertStringContainsString('Cannot delete index', $deletionException->getMessage());

        $configException = IndexNotFoundException::forConfiguration('test_index');
        $this->assertStringContainsString('Cannot configure index', $configException->getMessage());
    }

    /** @test */
    public function test_index_not_found_exception_context()
    {
        $exception = new IndexNotFoundException('test_index');
        $context = $exception->getContext();

        $this->assertArrayHasKey('index_name', $context);
        $this->assertArrayHasKey('index_not_found', $context);
        $this->assertEquals('test_index', $context['index_name']);
        $this->assertTrue($context['index_not_found']);
    }

    /** @test */
    public function test_rate_limit_exception_creation()
    {
        $exception = new RateLimitException(
            'Rate limit exceeded',
            10,
            time() + 3600,
            60
        );

        $this->assertEquals('Rate limit exceeded', $exception->getMessage());
        $this->assertEquals(10, $exception->getRateLimitRemaining());
        $this->assertEquals(60, $exception->getRetryAfter());
        $this->assertEquals(429, $exception->getCode());
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $exception->getErrorCode());
    }

    /** @test */
    public function test_rate_limit_exception_time_calculations()
    {
        $resetTime = time() + 3600; // 1 hour from now
        $exception = new RateLimitException('Test', 0, $resetTime, 60);

        $this->assertFalse($exception->hasRateLimitReset());
        $this->assertGreaterThan(3500, $exception->getSecondsUntilReset());
        $this->assertLessThanOrEqual(3600, $exception->getSecondsUntilReset());

        // Test with past reset time
        $pastException = new RateLimitException('Test', 0, time() - 100, 60);
        $this->assertTrue($pastException->hasRateLimitReset());
        $this->assertEquals(0, $pastException->getSecondsUntilReset());
    }

    /** @test */
    public function test_rate_limit_exception_static_methods()
    {
        $searchException = RateLimitException::forSearch(30);
        $this->assertStringContainsString('Search request rate limit', $searchException->getMessage());
        $this->assertEquals(30, $searchException->getRetryAfter());

        $indexingException = RateLimitException::forIndexing(120);
        $this->assertStringContainsString('Indexing request rate limit', $indexingException->getMessage());
        $this->assertEquals(120, $indexingException->getRetryAfter());

        $headersException = RateLimitException::fromHeaders([
            'X-RateLimit-Remaining' => '5',
            'X-RateLimit-Reset' => (string)(time() + 3600),
            'Retry-After' => '300'
        ]);
        $this->assertEquals(5, $headersException->getRateLimitRemaining());
        $this->assertEquals(300, $headersException->getRetryAfter());
    }

    /** @test */
    public function test_rate_limit_exception_context()
    {
        $resetTime = time() + 3600;
        $exception = new RateLimitException('Test', 10, $resetTime, 60);
        $context = $exception->getContext();

        $this->assertArrayHasKey('rate_limit_remaining', $context);
        $this->assertArrayHasKey('rate_limit_reset', $context);
        $this->assertArrayHasKey('rate_limit_reset_time', $context);
        $this->assertArrayHasKey('retry_after', $context);
        $this->assertArrayHasKey('rate_limit_exceeded', $context);

        $this->assertEquals(10, $context['rate_limit_remaining']);
        $this->assertEquals($resetTime, $context['rate_limit_reset']);
        $this->assertEquals(60, $context['retry_after']);
        $this->assertTrue($context['rate_limit_exceeded']);
    }

    /** @test */
    public function test_error_codes_descriptions()
    {
        $descriptions = ErrorCodes::getDescriptions();

        $this->assertIsArray($descriptions);
        $this->assertNotEmpty($descriptions);
        $this->assertArrayHasKey(ErrorCodes::CONNECTION_FAILED, $descriptions);
        $this->assertArrayHasKey(ErrorCodes::VALIDATION_FAILED, $descriptions);
        $this->assertArrayHasKey(ErrorCodes::INDEX_NOT_FOUND, $descriptions);
        $this->assertArrayHasKey(ErrorCodes::RATE_LIMIT_EXCEEDED, $descriptions);
    }

    /** @test */
    public function test_error_codes_individual_description()
    {
        $description = ErrorCodes::getDescription(ErrorCodes::CONNECTION_FAILED);
        $this->assertIsString($description);
        $this->assertStringContainsString('connection', strtolower($description));

        $unknownDescription = ErrorCodes::getDescription('UNKNOWN_ERROR_CODE');
        $this->assertEquals('Unknown error occurred', $unknownDescription);
    }

    /** @test */
    public function test_error_codes_retryability()
    {
        $this->assertTrue(ErrorCodes::isRetryable(ErrorCodes::CONNECTION_TIMEOUT));
        $this->assertTrue(ErrorCodes::isRetryable(ErrorCodes::INTERNAL_SERVER_ERROR));
        $this->assertTrue(ErrorCodes::isRetryable(ErrorCodes::SERVICE_UNAVAILABLE));

        $this->assertFalse(ErrorCodes::isRetryable(ErrorCodes::VALIDATION_FAILED));
        $this->assertFalse(ErrorCodes::isRetryable(ErrorCodes::INDEX_NOT_FOUND));
        $this->assertFalse(ErrorCodes::isRetryable(ErrorCodes::INVALID_API_KEY));
    }

    /** @test */
    public function test_error_codes_retry_delays()
    {
        $this->assertEquals(5, ErrorCodes::getRetryDelay(ErrorCodes::CONNECTION_TIMEOUT));
        $this->assertEquals(60, ErrorCodes::getRetryDelay(ErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertEquals(30, ErrorCodes::getRetryDelay(ErrorCodes::SERVICE_UNAVAILABLE));
        $this->assertEquals(0, ErrorCodes::getRetryDelay(ErrorCodes::VALIDATION_FAILED));
        $this->assertEquals(0, ErrorCodes::getRetryDelay('UNKNOWN_ERROR_CODE'));
    }

    /** @test */
    public function test_error_codes_categories()
    {
        $this->assertEquals('connection', ErrorCodes::getCategory(ErrorCodes::CONNECTION_FAILED));
        $this->assertEquals('validation', ErrorCodes::getCategory(ErrorCodes::VALIDATION_FAILED));
        $this->assertEquals('index', ErrorCodes::getCategory(ErrorCodes::INDEX_NOT_FOUND));
        $this->assertEquals('rate_limiting', ErrorCodes::getCategory(ErrorCodes::RATE_LIMIT_EXCEEDED));
        $this->assertEquals('server', ErrorCodes::getCategory(ErrorCodes::INTERNAL_SERVER_ERROR));
        $this->assertEquals('unknown', ErrorCodes::getCategory('INVALID_CODE'));
    }

    /** @test */
    public function test_exception_chaining()
    {
        $originalException = new \Exception('Original error');
        $oginiException = new OginiException('Wrapped error', 500, $originalException);

        $this->assertSame($originalException, $oginiException->getPrevious());
        $this->assertEquals('Original error', $oginiException->getPrevious()->getMessage());
    }

    /** @test */
    public function test_exception_to_array()
    {
        $exception = new OginiException('Test message', 500, null, ['data' => 'value'], 'TEST_CODE');
        $array = $exception->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('response', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);

        $this->assertEquals('Test message', $array['message']);
        $this->assertEquals(500, $array['code']);
        $this->assertEquals('TEST_CODE', $array['error_code']);
        $this->assertEquals(['data' => 'value'], $array['response']);
    }
}
