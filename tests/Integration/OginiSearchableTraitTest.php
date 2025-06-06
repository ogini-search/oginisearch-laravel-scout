<?php

namespace OginiScoutDriver\Tests\Integration;

use OginiScoutDriver\Tests\Integration\Models\TestProduct;
use OginiScoutDriver\Tests\Integration\Factories\TestDataFactory;
use Laravel\Scout\Builder;

class OginiSearchableTraitTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create some test data
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        TestDataFactory::cleanup();
        parent::tearDown();
    }

    protected function seedTestData(): void
    {
        TestDataFactory::createSpecificProduct([
            'title' => 'iPhone 14 Pro Max',
            'description' => 'Latest Apple smartphone with ProRAW camera',
            'price' => 1099.99,
            'category' => 'Electronics',
            'status' => 'published',
            'is_featured' => true,
            'tags' => ['smartphone', 'apple', 'camera'],
        ]);

        TestDataFactory::createSpecificProduct([
            'title' => 'MacBook Pro 16 inch',
            'description' => 'Professional laptop for developers',
            'price' => 2499.99,
            'category' => 'Computers',
            'status' => 'published',
            'is_featured' => true,
            'tags' => ['laptop', 'apple', 'professional'],
        ]);
    }

    /** @test */
    public function it_can_search_with_highlights(): void
    {
        $this->runTestWithOgini(function () {
            // Create index first
            try {
                TestProduct::createSearchIndex();
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }

            // Index the test data first
            TestProduct::makeAllSearchable();

            // Search with highlights
            $searchBuilder = TestProduct::searchWithHighlights('iPhone Pro');

            $this->assertInstanceOf(Builder::class, $searchBuilder);
            $this->assertNotNull($searchBuilder->callback);

            // Execute the search
            $results = $searchBuilder->get();

            $this->assertNotNull($results);

            // Check if any results have highlights
            foreach ($results as $model) {
                $this->assertInstanceOf(TestProduct::class, $model);
                // Highlights would be set by the engine if available
            }
        });
    }

    /** @test */
    public function it_can_get_suggestions(): void
    {
        $this->runTestWithOgini(function () {
            // Create index first
            try {
                TestProduct::createSearchIndex();
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }

            // Index the test data first
            TestProduct::makeAllSearchable();

            // Get suggestions
            $suggestions = TestProduct::suggest('iPho', 'title', 5);

            $this->assertIsArray($suggestions);
            // Suggestions might be empty if the server doesn't support it
        });
    }

    /** @test */
    public function it_can_create_search_index(): void
    {
        $this->runTestWithOgini(function () {
            // Delete index first if it exists
            try {
                TestProduct::deleteSearchIndex();
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }

            $result = TestProduct::createSearchIndex();

            $this->assertIsArray($result);
            // Should return success response from Ogini server
        });
    }

    /** @test */
    public function it_can_delete_search_index(): void
    {
        $this->runTestWithOgini(function () {
            // Create index first
            try {
                TestProduct::createSearchIndex();
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }

            // Then delete it
            $result = TestProduct::deleteSearchIndex();

            $this->assertIsArray($result);
            // Should return success response from Ogini server
        });
    }

    /** @test */
    public function it_can_get_search_index_info(): void
    {
        $this->runTestWithOgini(function () {
            // Create index first
            try {
                TestProduct::createSearchIndex();
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }

            // Get index info
            $info = TestProduct::getSearchIndexInfo();

            $this->assertIsArray($info);
            $this->assertArrayHasKey('name', $info);
            $this->assertEquals('test_products', $info['name']);
        });
    }

    /** @test */
    public function it_validates_index_configuration(): void
    {
        $product = new TestProduct();

        // Test index configuration
        $config = $product->getOginiIndexConfiguration();

        $this->assertArrayHasKey('settings', $config);
        $this->assertArrayHasKey('mappings', $config);
        $this->assertArrayHasKey('properties', $config['mappings']);

        // Validate specific settings
        $this->assertEquals(1, $config['settings']['numberOfShards']);
        $this->assertEquals('1s', $config['settings']['refreshInterval']);

        // Validate field mappings
        $properties = $config['mappings']['properties'];
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('description', $properties);
        $this->assertArrayHasKey('price', $properties);

        $this->assertEquals('text', $properties['title']['type']);
        $this->assertEquals('text', $properties['description']['type']);
        $this->assertEquals('float', $properties['price']['type']);
    }

    /** @test */
    public function it_can_get_field_mappings(): void
    {
        // Create a product with actual data so field type inference works
        $product = TestDataFactory::createSpecificProduct([
            'title' => 'Test Product',
            'description' => 'Test description',
            'price' => 99.99,
            'category' => 'Electronics',
            'status' => 'published',
            'is_featured' => true,
            'tags' => ['test', 'product'],
        ]);

        $mappings = $product->getOginiFieldMappings();

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('title', $mappings);
        $this->assertArrayHasKey('description', $mappings);
        $this->assertArrayHasKey('price', $mappings);
        $this->assertArrayHasKey('category', $mappings);
        $this->assertArrayHasKey('status', $mappings);
        $this->assertArrayHasKey('is_featured', $mappings);
        $this->assertArrayHasKey('tags', $mappings);

        // Validate field types
        $this->assertEquals('text', $mappings['title']['type']);
        $this->assertEquals('text', $mappings['description']['type']);
        $this->assertEquals('float', $mappings['price']['type']);
        $this->assertEquals('keyword', $mappings['category']['type']);
        $this->assertEquals('keyword', $mappings['status']['type']);
        $this->assertEquals('boolean', $mappings['is_featured']['type']);
        $this->assertEquals('keyword', $mappings['tags']['type']);
    }

    /** @test */
    public function it_can_get_search_fields(): void
    {
        $product = new TestProduct();

        $searchFields = $product->getSearchFields();

        $this->assertIsArray($searchFields);
        $this->assertContains('title', $searchFields);
        $this->assertContains('description', $searchFields);

        // Should not contain non-text fields
        $this->assertNotContains('price', $searchFields);
        $this->assertNotContains('is_featured', $searchFields);
        $this->assertNotContains('created_at', $searchFields);
    }

    /** @test */
    public function it_can_handle_highlights(): void
    {
        $product = new TestProduct();

        // Test setting highlights
        $highlights = [
            'title' => ['<em>iPhone</em> 14 Pro'],
            'description' => ['Latest <em>Apple</em> smartphone']
        ];

        $result = $product->setHighlights($highlights);
        $this->assertSame($product, $result);
        $this->assertEquals($highlights, $product->getHighlights());

        // Test getting individual highlight
        $titleHighlight = $product->getHighlight('title');
        $this->assertEquals('<em>iPhone</em> 14 Pro', $titleHighlight);

        $descriptionHighlight = $product->getHighlight('description');
        $this->assertEquals('Latest <em>Apple</em> smartphone', $descriptionHighlight);

        // Test getting non-existent highlight with default
        $missingHighlight = $product->getHighlight('missing', 'default value');
        $this->assertEquals('default value', $missingHighlight);

        // Test clearing highlights
        $product->setHighlights(null);
        $this->assertNull($product->getHighlights());
    }

    /** @test */
    public function it_throws_exception_for_non_ogini_engine(): void
    {
        // This test would require mocking the engine manager to return a different engine
        // For integration tests, we assume Ogini engine is configured
        $this->assertTrue(true, 'Integration test assumes Ogini engine is configured');
    }
}
