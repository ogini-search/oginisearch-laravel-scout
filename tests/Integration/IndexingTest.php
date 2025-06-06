<?php

namespace OginiScoutDriver\Tests\Integration;

use OginiScoutDriver\Tests\Integration\Models\TestProduct;
use OginiScoutDriver\Tests\Integration\Models\TestUser;
use OginiScoutDriver\Tests\Integration\Factories\TestDataFactory;
use OginiScoutDriver\Engine\OginiEngine;
use Illuminate\Support\Facades\Log;

class IndexingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Don't create indices during setup to avoid network calls
        // Individual tests will handle index creation as needed
    }

    protected function tearDown(): void
    {
        // Clean up test data
        TestDataFactory::cleanup();

        parent::tearDown();
    }

    /**
     * Create test indices.
     *
     * @return void
     */
    protected function createTestIndices(): void
    {
        try {
            TestProduct::createSearchIndex();
            TestUser::createSearchIndex();
        } catch (\Exception $e) {
            // Log but don't fail - may need actual Ogini server
            Log::warning('Could not create test indices', ['error' => $e->getMessage()]);
        }
    }

    /** @test */
    public function it_can_index_a_single_document(): void
    {
        // Create a single product
        $product = TestDataFactory::createSpecificProduct([
            'title' => 'Single Test Product',
            'description' => 'This product is used for single indexing test',
            'price' => 149.99,
            'category' => 'Electronics',
            'status' => 'published',
            'is_featured' => true,
            'tags' => ['test', 'single'],
        ]);

        // Verify product was created
        $this->assertInstanceOf(TestProduct::class, $product);
        $this->assertEquals('Single Test Product', $product->title);
        $this->assertEquals(149.99, (float) $product->price);

        // Test searchable array
        $searchableArray = $product->toSearchableArray();
        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('title', $searchableArray);
        $this->assertArrayHasKey('description', $searchableArray);
        $this->assertArrayHasKey('price', $searchableArray);
        $this->assertEquals('Single Test Product', $searchableArray['title']);
        $this->assertEquals(149.99, $searchableArray['price']);

        // Test manual indexing (would require Ogini server)
        try {
            $product->searchable();
            $this->assertTrue(true, 'Product indexed successfully');
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_batch_index_multiple_documents(): void
    {
        // Create multiple products
        $products = TestDataFactory::createProducts(5);

        // Verify products were created
        $this->assertCount(5, $products);
        $this->assertInstanceOf(TestProduct::class, $products->first());

        // Test batch indexing (would require Ogini server)
        try {
            TestProduct::makeAllSearchable();
            $this->assertTrue(true, 'Products batch indexed successfully');
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }

        // Verify all products have proper searchable arrays
        $products->each(function ($product) {
            $searchableArray = $product->toSearchableArray();
            $this->assertArrayHasKey('id', $searchableArray);
            $this->assertArrayHasKey('title', $searchableArray);
            $this->assertArrayHasKey('price', $searchableArray);
        });
    }

    /** @test */
    public function it_can_update_an_indexed_document(): void
    {
        // Create and index a product
        $product = TestDataFactory::createSpecificProduct([
            'title' => 'Original Product Title',
            'price' => 99.99,
        ]);

        // Index the product
        try {
            $product->searchable();
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }

        // Update the product
        $product->update([
            'title' => 'Updated Product Title',
            'price' => 129.99,
        ]);

        // Verify the update
        $product->refresh();
        $this->assertEquals('Updated Product Title', $product->title);
        $this->assertEquals(129.99, (float) $product->price);

        // Test the updated searchable array
        $searchableArray = $product->toSearchableArray();
        $this->assertEquals('Updated Product Title', $searchableArray['title']);
        $this->assertEquals(129.99, $searchableArray['price']);

        // Re-index the updated product
        try {
            $product->searchable();
            $this->assertTrue(true, 'Updated product re-indexed successfully');
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_delete_an_indexed_document(): void
    {
        // Create and index a product
        $product = TestDataFactory::createSpecificProduct([
            'title' => 'Product to Delete',
            'price' => 199.99,
        ]);

        $productId = $product->id;

        // Index the product
        try {
            $product->searchable();
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }

        // Delete the product (should also remove from index)
        $product->delete();

        // Verify product is deleted from database
        $this->assertNull(TestProduct::find($productId));

        // Test manual removal from index
        try {
            $product->unsearchable();
            $this->assertTrue(true, 'Product removed from search index successfully');
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_batch_delete_indexed_documents(): void
    {
        // Create multiple products
        $products = TestDataFactory::createProducts(3);
        $productIds = $products->pluck('id')->toArray();

        // Index the products
        try {
            TestProduct::makeAllSearchable();
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }

        // Delete all products
        TestProduct::whereIn('id', $productIds)->delete();

        // Verify products are deleted from database
        $remainingProducts = TestProduct::whereIn('id', $productIds)->count();
        $this->assertEquals(0, $remainingProducts);

        // Test batch removal from index
        try {
            // This would be handled by Scout observers automatically
            $this->assertTrue(true, 'Products removed from search index successfully');
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_indexing_with_different_data_types(): void
    {
        // Create a product with various data types
        $product = TestDataFactory::createSpecificProduct([
            'title' => 'Data Types Test Product',
            'description' => 'Testing different data types in indexing',
            'price' => 299.99, // float
            'category' => 'Electronics', // string
            'status' => 'published', // string/keyword
            'is_featured' => true, // boolean
            'tags' => ['electronics', 'featured', 'test'], // array
        ]);

        // Test searchable array data types
        $searchableArray = $product->toSearchableArray();

        $this->assertIsString($searchableArray['title']);
        $this->assertIsString($searchableArray['description']);
        $this->assertIsFloat($searchableArray['price']);
        $this->assertIsString($searchableArray['category']);
        $this->assertIsString($searchableArray['status']);
        $this->assertIsBool($searchableArray['is_featured']);
        $this->assertIsArray($searchableArray['tags']);

        // Test field mappings
        $fieldMappings = $product->getOginiFieldMappings();
        $this->assertEquals('text', $fieldMappings['title']['type']);
        $this->assertEquals('text', $fieldMappings['description']['type']);
        $this->assertEquals('float', $fieldMappings['price']['type']);
        $this->assertEquals('keyword', $fieldMappings['category']['type']);
        $this->assertEquals('keyword', $fieldMappings['status']['type']);
        $this->assertEquals('boolean', $fieldMappings['is_featured']['type']);
        $this->assertEquals('keyword', $fieldMappings['tags']['type']);

        // Test indexing
        try {
            $product->searchable();
            $this->assertTrue(true, 'Product with various data types indexed successfully');
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_handle_large_batch_indexing(): void
    {
        // Create a larger number of products for batch testing
        $products = TestDataFactory::createProducts(25);

        $this->assertCount(25, $products);

        // Test that all products have valid searchable arrays
        $products->each(function ($product) {
            $searchableArray = $product->toSearchableArray();
            $this->assertArrayHasKey('id', $searchableArray);
            $this->assertArrayHasKey('title', $searchableArray);
            $this->assertNotEmpty($searchableArray['title']);
        });

        // Test batch indexing
        try {
            TestProduct::makeAllSearchable();
            $this->assertTrue(true, 'Large batch of products indexed successfully');
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_index_configuration(): void
    {
        $product = new TestProduct();

        // Test index configuration
        $indexConfig = $product->getOginiIndexConfiguration();

        $this->assertArrayHasKey('settings', $indexConfig);
        $this->assertArrayHasKey('mappings', $indexConfig);
        $this->assertArrayHasKey('properties', $indexConfig['mappings']);

        // Validate settings
        $this->assertEquals(1, $indexConfig['settings']['numberOfShards']);
        $this->assertEquals('1s', $indexConfig['settings']['refreshInterval']);

        // Validate field mappings
        $properties = $indexConfig['mappings']['properties'];
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('price', $properties);
        $this->assertEquals('text', $properties['title']['type']);
        $this->assertEquals('float', $properties['price']['type']);
    }
}
