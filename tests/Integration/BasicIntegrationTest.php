<?php

namespace OginiScoutDriver\Tests\Integration;

use OginiScoutDriver\Tests\Integration\Models\TestProduct;
use OginiScoutDriver\Tests\Integration\Factories\TestDataFactory;
use Illuminate\Support\Facades\DB;

/**
 * @group integration-tests
 */
class BasicIntegrationTest extends IntegrationTestCase
{
    /** @test */
    public function it_can_setup_test_environment(): void
    {
        // Test that the basic environment is working
        $this->assertTrue(true, 'Test environment setup successful');

        // Test database connection
        $this->assertNotNull(DB::connection('testing'));
    }

    /** @test */
    public function it_can_create_test_models(): void
    {
        // Test creating a product without indexing
        $product = TestDataFactory::createSpecificProduct([
            'title' => 'Test Product',
            'price' => 99.99,
        ]);

        $this->assertInstanceOf(TestProduct::class, $product);
        $this->assertEquals('Test Product', $product->title);
        $this->assertEquals(99.99, (float) $product->price);
        $this->assertDatabaseHas('test_products', ['title' => 'Test Product']);
    }

    /** @test */
    public function it_can_generate_searchable_arrays(): void
    {
        // Test generating searchable arrays without indexing
        $product = TestDataFactory::createSpecificProduct([
            'title' => 'Searchable Test Product',
            'description' => 'Testing searchable arrays',
            'price' => 149.99,
            'category' => 'Electronics',
            'tags' => ['test', 'searchable'],
        ]);

        $searchableArray = $product->toSearchableArray();

        $this->assertIsArray($searchableArray);
        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('title', $searchableArray);
        $this->assertArrayHasKey('description', $searchableArray);
        $this->assertArrayHasKey('price', $searchableArray);
        $this->assertArrayHasKey('tags', $searchableArray);

        $this->assertEquals('Searchable Test Product', $searchableArray['title']);
        $this->assertEquals(149.99, $searchableArray['price']);
        $this->assertEquals(['test', 'searchable'], $searchableArray['tags']);
    }

    /** @test */
    public function it_can_configure_search_indices(): void
    {
        // Test index configuration without creating actual indices
        $product = new TestProduct();

        $indexConfig = $product->getOginiIndexConfiguration();
        $this->assertIsArray($indexConfig);
        $this->assertArrayHasKey('settings', $indexConfig);
        $this->assertArrayHasKey('mappings', $indexConfig);

        $fieldMappings = $product->getOginiFieldMappings();
        $this->assertIsArray($fieldMappings);
        $this->assertArrayHasKey('title', $fieldMappings);
        $this->assertArrayHasKey('price', $fieldMappings);

        $searchFields = $product->getSearchFields();
        $this->assertIsArray($searchFields);
        $this->assertContains('title', $searchFields);
        $this->assertContains('description', $searchFields);
    }

    /** @test */
    public function it_can_create_search_builders(): void
    {
        // Test creating search builders without executing searches
        $searchBuilder = TestProduct::search('test query');

        $this->assertInstanceOf(\Laravel\Scout\Builder::class, $searchBuilder);
        $this->assertEquals('test query', $searchBuilder->query);
        $this->assertEquals(TestProduct::class, get_class($searchBuilder->model));

        // Test adding filters
        $filteredBuilder = TestProduct::search('')
            ->where('category', 'Electronics')
            ->where('price', '>', 100)
            ->orderBy('title', 'asc')
            ->take(10);

        $this->assertCount(2, $filteredBuilder->wheres);
        $this->assertCount(1, $filteredBuilder->orders);
        $this->assertEquals(10, $filteredBuilder->limit);
    }

    /** @test */
    public function it_can_handle_model_relationships(): void
    {
        // Test that models work with standard Eloquent features
        $product1 = TestDataFactory::createSpecificProduct(['title' => 'Product 1']);
        $product2 = TestDataFactory::createSpecificProduct(['title' => 'Product 2']);

        $this->assertCount(2, TestProduct::all());

        $foundProduct = TestProduct::where('title', 'Product 1')->first();
        $this->assertNotNull($foundProduct);
        $this->assertEquals('Product 1', $foundProduct->title);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        TestDataFactory::cleanup();

        parent::tearDown();
    }
}
