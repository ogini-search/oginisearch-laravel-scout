<?php

namespace OginiScoutDriver\Tests\Unit\Exceptions;

use OginiScoutDriver\Exceptions\OginiException;
use PHPUnit\Framework\TestCase;
use Exception;

class OginiExceptionTest extends TestCase
{
    public function testExceptionCreation(): void
    {
        $message = 'Test error message';
        $code = 404;
        $response = ['error' => 'Not found'];
        $errorCode = 'NOT_FOUND';

        $exception = new OginiException($message, $code, null, $response, $errorCode);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertEquals($response, $exception->getResponse());
        $this->assertEquals($errorCode, $exception->getErrorCode());
    }

    public function testExceptionWithPreviousException(): void
    {
        $previousException = new Exception('Previous error');
        $exception = new OginiException('Current error', 500, $previousException);

        $this->assertEquals($previousException, $exception->getPrevious());
    }

    public function testIsClientErrorMethod(): void
    {
        $clientError = new OginiException('Client error', 404);
        $serverError = new OginiException('Server error', 500);
        $connectionError = new OginiException('Connection error', 0);

        $this->assertTrue($clientError->isClientError());
        $this->assertFalse($serverError->isClientError());
        $this->assertFalse($connectionError->isClientError());
    }

    public function testIsServerErrorMethod(): void
    {
        $clientError = new OginiException('Client error', 404);
        $serverError = new OginiException('Server error', 500);
        $connectionError = new OginiException('Connection error', 0);

        $this->assertFalse($clientError->isServerError());
        $this->assertTrue($serverError->isServerError());
        $this->assertFalse($connectionError->isServerError());
    }

    public function testIsConnectionErrorMethod(): void
    {
        $clientError = new OginiException('Client error', 404);
        $serverError = new OginiException('Server error', 500);
        $connectionError = new OginiException('Connection error', 0);

        $this->assertFalse($clientError->isConnectionError());
        $this->assertFalse($serverError->isConnectionError());
        $this->assertTrue($connectionError->isConnectionError());
    }

    public function testGetDetailedMessage(): void
    {
        $exception = new OginiException('Test error', 404, null, null, 'NOT_FOUND');
        $detailedMessage = $exception->getDetailedMessage();

        $this->assertStringContainsString('Test error', $detailedMessage);
        $this->assertStringContainsString('Error Code: NOT_FOUND', $detailedMessage);
        $this->assertStringContainsString('HTTP Status: 404', $detailedMessage);
    }

    public function testGetDetailedMessageWithoutErrorCode(): void
    {
        $exception = new OginiException('Test error', 404);
        $detailedMessage = $exception->getDetailedMessage();

        $this->assertStringContainsString('Test error', $detailedMessage);
        $this->assertStringNotContainsString('Error Code:', $detailedMessage);
        $this->assertStringContainsString('HTTP Status: 404', $detailedMessage);
    }

    public function testToArrayMethod(): void
    {
        $message = 'Test error';
        $code = 404;
        $response = ['error' => 'Not found'];
        $errorCode = 'NOT_FOUND';

        $exception = new OginiException($message, $code, null, $response, $errorCode);
        $array = $exception->toArray();

        $this->assertEquals($message, $array['message']);
        $this->assertEquals($code, $array['code']);
        $this->assertEquals($errorCode, $array['error_code']);
        $this->assertEquals($response, $array['response']);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
    }
}
