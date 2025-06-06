<?php

namespace OginiScoutDriver\Tests\Unit\Traits;

use Orchestra\Testbench\TestCase;
use OginiScoutDriver\Traits\OginiSearchable;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\OginiServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class TestModelWithOginiSearchable extends Model
{
    use OginiSearchable;

    protected $fillable = ['title', 'content', 'status', 'price', 'is_featured'];

    public function toSearchableArray(): array
    {
        return [
            'title' => 'Test Product',
            'content' => 'This is a test product description',
            'status' => 'published',
            'price' => 99.99,
            'is_featured' => true,
            'created_at' => '2023-01-01T00:00:00Z',
            'category_id' => 1,
            'tags' => ['electronics', 'gadgets'],
        ];
    }

    public function searchableAs(): string
    {
        return 'test_products';
    }
}

class OginiSearchableTest extends TestCase
{
    protected TestModelWithOginiSearchable $model;

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            ScoutServiceProvider::class,
            OginiServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Scout configuration
        $app['config']->set('scout.driver', 'ogini');
        $app['config']->set('scout.queue', false);

        // OginiSearch configuration
        $app['config']->set('ogini.base_url', 'http://localhost:3000');
        $app['config']->set('ogini.api_key', 'test-api-key');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new TestModelWithOginiSearchable();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // Highlight Tests

    public function testGetHighlights(): void
    {
        $highlights = [
            'title' => ['<em>Test</em> Product'],
            'content' => ['This is a <em>test</em> product description']
        ];

        $this->model->setHighlights($highlights);

        $this->assertEquals($highlights, $this->model->getHighlights());
    }

    public function testGetHighlightsWhenNull(): void
    {
        $this->assertNull($this->model->getHighlights());
    }

    public function testSetHighlights(): void
    {
        $highlights = ['title' => ['<em>Test</em> Product']];

        $result = $this->model->setHighlights($highlights);

        $this->assertSame($this->model, $result);
        $this->assertEquals($highlights, $this->model->getHighlights());
    }

    public function testSetHighlightsToNull(): void
    {
        $this->model->setHighlights(['title' => ['test']]);
        $this->model->setHighlights(null);

        $this->assertNull($this->model->getHighlights());
    }

    public function testGetHighlight(): void
    {
        $this->model->setHighlights([
            'title' => ['<em>Test</em> Product', 'Another <em>test</em>'],
            'content' => ['Description with <em>test</em>']
        ]);

        $titleHighlight = $this->model->getHighlight('title');
        $contentHighlight = $this->model->getHighlight('content');
        $missingHighlight = $this->model->getHighlight('missing', 'default');

        $this->assertEquals('<em>Test</em> Product ... Another <em>test</em>', $titleHighlight);
        $this->assertEquals('Description with <em>test</em>', $contentHighlight);
        $this->assertEquals('default', $missingHighlight);
    }

    public function testGetHighlightWhenNoHighlights(): void
    {
        $result = $this->model->getHighlight('title', 'default value');

        $this->assertEquals('default value', $result);
    }

    // Index Configuration Tests

    public function testGetOginiIndexConfiguration(): void
    {
        $config = $this->model->getOginiIndexConfiguration();

        $this->assertArrayHasKey('settings', $config);
        $this->assertArrayHasKey('mappings', $config);
        $this->assertArrayHasKey('properties', $config['mappings']);
        $this->assertEquals(1, $config['settings']['numberOfShards']);
        $this->assertEquals('1s', $config['settings']['refreshInterval']);
    }

    public function testGetOginiFieldMappings(): void
    {
        $mappings = $this->model->getOginiFieldMappings();

        // Test inferred field types
        $this->assertEquals('text', $mappings['title']['type']);
        $this->assertEquals('text', $mappings['content']['type']);
        $this->assertEquals('keyword', $mappings['status']['type']);
        $this->assertEquals('float', $mappings['price']['type']);
        $this->assertEquals('boolean', $mappings['is_featured']['type']);
        $this->assertEquals('date', $mappings['created_at']['type']);
        $this->assertEquals('keyword', $mappings['category_id']['type']);
        $this->assertEquals('keyword', $mappings['tags']['type']);
    }

    public function testGetSearchFields(): void
    {
        $searchFields = $this->model->getSearchFields();

        // Should return text fields
        $this->assertContains('title', $searchFields);
        $this->assertContains('content', $searchFields);
        $this->assertNotContains('price', $searchFields);
        $this->assertNotContains('is_featured', $searchFields);
    }

    // Field Type Inference Tests

    public function testInferFieldTypeForDateFields(): void
    {
        $reflection = new \ReflectionClass($this->model);
        $method = $reflection->getMethod('inferFieldType');
        $method->setAccessible(true);

        $result1 = $method->invoke($this->model, 'created_at', '2023-01-01');
        $result2 = $method->invoke($this->model, 'updated_at', '2023-01-01');
        $result3 = $method->invoke($this->model, 'published_date', '2023-01-01');
        $result4 = $method->invoke($this->model, 'some_date', '2023-01-01');

        $this->assertEquals('date', $result1['type']);
        $this->assertEquals('date', $result2['type']);
        $this->assertEquals('date', $result3['type']);
        $this->assertEquals('date', $result4['type']);
    }

    public function testInferFieldTypeForIdFields(): void
    {
        $reflection = new \ReflectionClass($this->model);
        $method = $reflection->getMethod('inferFieldType');
        $method->setAccessible(true);

        $result1 = $method->invoke($this->model, 'id', 1);
        $result2 = $method->invoke($this->model, 'user_id', 1);
        $result3 = $method->invoke($this->model, 'category_id', 1);

        $this->assertEquals('keyword', $result1['type']);
        $this->assertEquals('keyword', $result2['type']);
        $this->assertEquals('keyword', $result3['type']);
    }

    public function testInferFieldTypeForPrimitiveTypes(): void
    {
        $reflection = new \ReflectionClass($this->model);
        $method = $reflection->getMethod('inferFieldType');
        $method->setAccessible(true);

        $boolResult = $method->invoke($this->model, 'is_active', true);
        $intResult = $method->invoke($this->model, 'count', 10);
        $floatResult = $method->invoke($this->model, 'price', 99.99);
        $arrayResult = $method->invoke($this->model, 'tags', ['tag1', 'tag2']);
        $textResult = $method->invoke($this->model, 'description', 'Some text');

        $this->assertEquals('boolean', $boolResult['type']);
        $this->assertEquals('integer', $intResult['type']);
        $this->assertEquals('float', $floatResult['type']);
        $this->assertEquals('keyword', $arrayResult['type']);
        $this->assertEquals('text', $textResult['type']);
        $this->assertEquals('standard', $textResult['analyzer']);
    }

    // Static Methods Tests - These are better tested in integration tests
    // where we have the full Laravel Scout environment set up

    public function testSearchWithHighlights(): void
    {
        // Test that the method returns a Builder instance
        // We can't easily test the callback without complex mocking
        $result = TestModelWithOginiSearchable::searchWithHighlights('test query');

        $this->assertInstanceOf(Builder::class, $result);
    }

    public function testStaticMethodsAreAvailable(): void
    {
        // Just test that the static methods exist and are callable
        $this->assertTrue(method_exists(TestModelWithOginiSearchable::class, 'suggest'));
        $this->assertTrue(method_exists(TestModelWithOginiSearchable::class, 'createSearchIndex'));
        $this->assertTrue(method_exists(TestModelWithOginiSearchable::class, 'deleteSearchIndex'));
        $this->assertTrue(method_exists(TestModelWithOginiSearchable::class, 'getSearchIndexInfo'));
        $this->assertTrue(method_exists(TestModelWithOginiSearchable::class, 'searchWithHighlights'));
    }
}
