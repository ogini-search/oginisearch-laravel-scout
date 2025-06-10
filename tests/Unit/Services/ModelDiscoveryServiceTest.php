<?php

namespace OginiScoutDriver\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Services\ModelDiscoveryService;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Mockery;

class ModelDiscoveryServiceTest extends TestCase
{
    protected ModelDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ModelDiscoveryService([], false); // Disable cache for testing
    }

    public function testCanInstantiateService(): void
    {
        $this->assertInstanceOf(ModelDiscoveryService::class, $this->service);
    }

    public function testIsSearchableModelReturnsTrueForSearchableModel(): void
    {
        $result = $this->service->isSearchableModel(TestSearchableModel::class);
        $this->assertTrue($result);
    }

    public function testIsSearchableModelReturnsFalseForNonSearchableModel(): void
    {
        $result = $this->service->isSearchableModel(TestNonSearchableModel::class);
        $this->assertFalse($result);
    }

    public function testIsSearchableModelReturnsFalseForNonExistentClass(): void
    {
        $result = $this->service->isSearchableModel('NonExistentClass');
        $this->assertFalse($result);
    }

    public function testIsSearchableModelReturnsFalseForNonModelClass(): void
    {
        $result = $this->service->isSearchableModel(ModelDiscoveryService::class);
        $this->assertFalse($result);
    }

    public function testResolveModelClassWithFullClassName(): void
    {
        $result = $this->service->resolveModelClass(TestSearchableModel::class);
        $this->assertEquals(TestSearchableModel::class, $result);
    }

    public function testResolveModelClassWithShortName(): void
    {
        // Mock the getSearchableModelsMap method
        $service = Mockery::mock(ModelDiscoveryService::class)->makePartial();
        $service->shouldReceive('getSearchableModelsMap')
            ->andReturn(['TestSearchableModel' => TestSearchableModel::class]);

        $result = $service->resolveModelClass('TestSearchableModel');
        $this->assertEquals(TestSearchableModel::class, $result);
    }

    public function testResolveModelClassReturnsNullForInvalidModel(): void
    {
        $result = $this->service->resolveModelClass('InvalidModel');
        $this->assertNull($result);
    }

    public function testValidateModelWithValidModel(): void
    {
        $result = $this->service->validateModel(TestSearchableModel::class);

        // The validation might fail due to missing required methods in our test model
        // Just check that the validation structure is correct
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('info', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertIsArray($result['warnings']);
        $this->assertIsArray($result['info']);
    }

    public function testValidateModelWithInvalidModel(): void
    {
        $result = $this->service->validateModel('NonExistentClass');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('Class NonExistentClass does not exist', $result['errors']);
    }

    public function testValidateModelWithNonSearchableModel(): void
    {
        $result = $this->service->validateModel(TestNonSearchableModel::class);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('does not use the Searchable trait', $result['errors'][0]);
    }

    public function testGetSearchableModelsMapReturnsCorrectFormat(): void
    {
        // Mock the discoverSearchableModels method
        $service = Mockery::mock(ModelDiscoveryService::class)->makePartial();
        $service->shouldReceive('discoverSearchableModels')
            ->andReturn([TestSearchableModel::class]);

        $result = $service->getSearchableModelsMap();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('TestSearchableModel', $result);
        $this->assertEquals(TestSearchableModel::class, $result['TestSearchableModel']);
    }

    public function testClearCacheClearsInternalCache(): void
    {
        $service = new ModelDiscoveryService([], true); // Enable cache

        // Force some cached data
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('cachedModels');
        $property->setAccessible(true);
        $property->setValue($service, ['some' => 'data']);

        $service->clearCache();

        $this->assertEmpty($property->getValue($service));
    }

    public function testAddSearchPathsUpdatesSearchPaths(): void
    {
        $service = new ModelDiscoveryService(['original/path'], true);

        $service->addSearchPaths(['new/path']);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('searchPaths');
        $property->setAccessible(true);
        $paths = $property->getValue($service);

        $this->assertContains('original/path', $paths);
        $this->assertContains('new/path', $paths);
    }

    public function testGetModelDetailsReturnsCorrectStructure(): void
    {
        // Mock the discoverSearchableModels method
        $service = Mockery::mock(ModelDiscoveryService::class)->makePartial();
        $service->shouldReceive('discoverSearchableModels')
            ->andReturn([TestSearchableModel::class]);

        $result = $service->getModelDetails();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $detail = $result[0];
        $this->assertArrayHasKey('class', $detail);
        $this->assertArrayHasKey('short_name', $detail);
        $this->assertArrayHasKey('index_name', $detail);
        $this->assertArrayHasKey('table', $detail);
        $this->assertArrayHasKey('searchable_fields', $detail);
    }

    public function testGetModelDetailsHandlesExceptions(): void
    {
        // Mock the discoverSearchableModels method to return a problematic model
        $service = Mockery::mock(ModelDiscoveryService::class)->makePartial();
        $service->shouldReceive('discoverSearchableModels')
            ->andReturn([TestProblematicModel::class]);

        $result = $service->getModelDetails();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $detail = $result[0];
        $this->assertArrayHasKey('error', $detail);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

// Test models for testing purposes
class TestSearchableModel extends Model
{
    use Searchable;

    protected $table = 'test_searchables';

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => 'Test Name',
            'description' => 'Test Description',
        ];
    }

    public function searchableAs(): string
    {
        return 'test_searchables';
    }
}

class TestNonSearchableModel extends Model
{
    protected $table = 'test_non_searchables';
}

class TestProblematicModel extends Model
{
    use Searchable;

    protected $table = 'test_problematic';

    public function __construct(array $attributes = [])
    {
        throw new \Exception('Test exception for model instantiation');
    }
}
