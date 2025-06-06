<?php

namespace OginiScoutDriver\Tests\Unit\Facades;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Facades\Ogini;
use OginiScoutDriver\Facades\AsyncOgini;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Client\AsyncOginiClient;
use Illuminate\Support\Facades\Facade;
use Mockery;

class FacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function ogini_facade_returns_correct_accessor(): void
    {
        $reflection = new \ReflectionClass(Ogini::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(new Ogini());
        $this->assertEquals(OginiClient::class, $accessor);
    }

    /** @test */
    public function async_ogini_facade_returns_correct_accessor(): void
    {
        $reflection = new \ReflectionClass(AsyncOgini::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(new AsyncOgini());
        $this->assertEquals(AsyncOginiClient::class, $accessor);
    }

    /** @test */
    public function ogini_facade_extends_base_facade(): void
    {
        $this->assertInstanceOf(Facade::class, new Ogini());
    }

    /** @test */
    public function async_ogini_facade_extends_base_facade(): void
    {
        $this->assertInstanceOf(Facade::class, new AsyncOgini());
    }

    /** @test */
    public function ogini_facade_has_method_documentation(): void
    {
        $reflection = new \ReflectionClass(Ogini::class);
        $docComment = $reflection->getDocComment();

        $this->assertStringContainsString('@method static array search(', $docComment);
        $this->assertStringContainsString('@method static array getQuerySuggestions(', $docComment);
        $this->assertStringContainsString('@method static array addSynonyms(', $docComment);
        $this->assertStringContainsString('@method static array configureStopwords(', $docComment);
    }

    /** @test */
    public function async_ogini_facade_has_method_documentation(): void
    {
        $reflection = new \ReflectionClass(AsyncOgini::class);
        $docComment = $reflection->getDocComment();

        $this->assertStringContainsString('@method static', $docComment);
        $this->assertStringContainsString('indexDocumentAsync', $docComment);
        $this->assertStringContainsString('searchAsync', $docComment);
        $this->assertStringContainsString('executeParallel', $docComment);
    }
}
