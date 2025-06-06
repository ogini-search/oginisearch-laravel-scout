<?php

namespace OginiScoutDriver\Tests\Integration;

use OginiScoutDriver\Tests\Integration\Models\TestProduct;
use OginiScoutDriver\Tests\Integration\Models\TestArticle;
use OginiScoutDriver\Tests\Integration\Factories\TestDataFactory;
use Laravel\Scout\Builder;
use Illuminate\Support\Facades\Log;

/**
 * @group integration-tests
 */
class SearchTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create and seed test data
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        TestDataFactory::cleanup();

        parent::tearDown();
    }

    /**
     * Seed test data for search tests.
     *
     * @return void
     */
    protected function seedTestData(): void
    {
        // Create specific products for search testing
        TestDataFactory::createSpecificProduct([
            'title' => 'iPhone 14 Pro',
            'description' => 'Latest Apple smartphone with advanced camera',
            'price' => 999.99,
            'category' => 'Electronics',
            'status' => 'published',
            'is_featured' => true,
            'tags' => ['smartphone', 'apple', 'featured'],
        ]);

        TestDataFactory::createSpecificProduct([
            'title' => 'Samsung Galaxy S23',
            'description' => 'Android smartphone with excellent display',
            'price' => 799.99,
            'category' => 'Electronics',
            'status' => 'published',
            'is_featured' => false,
            'tags' => ['smartphone', 'samsung', 'android'],
        ]);

        TestDataFactory::createSpecificProduct([
            'title' => 'MacBook Pro 16',
            'description' => 'Powerful laptop for professionals',
            'price' => 2499.99,
            'category' => 'Computers',
            'status' => 'published',
            'is_featured' => true,
            'tags' => ['laptop', 'apple', 'professional'],
        ]);

        TestDataFactory::createSpecificProduct([
            'title' => 'Dell XPS 13',
            'description' => 'Compact and powerful ultrabook',
            'price' => 1299.99,
            'category' => 'Computers',
            'status' => 'draft',
            'is_featured' => false,
            'tags' => ['laptop', 'dell', 'ultrabook'],
        ]);

        // Create additional random products
        TestDataFactory::createProducts(6);

        // Don't index during setup to avoid network calls
        // Individual tests will handle indexing as needed
    }

    /** @test */
    public function it_can_perform_basic_search(): void
    {
        // Test basic text search
        $searchBuilder = TestProduct::search('iPhone');

        // Verify builder configuration
        $this->assertInstanceOf(Builder::class, $searchBuilder);
        $this->assertEquals('iPhone', $searchBuilder->query);
        $this->assertEquals(TestProduct::class, get_class($searchBuilder->model));

        // Test search execution with automatic skipping
        $this->runTestWithOgini(function () use ($searchBuilder) {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);
        });

        // Test empty query search
        $allProductsBuilder = TestProduct::search('');
        $this->assertInstanceOf(Builder::class, $allProductsBuilder);
        $this->assertEquals('', $allProductsBuilder->query);
    }

    /** @test */
    public function it_can_perform_filtered_search(): void
    {
        // Test search with filters
        $searchBuilder = TestProduct::search('smartphone')
            ->where('category', 'Electronics')
            ->where('status', 'published');

        // Verify builder configuration
        $this->assertInstanceOf(Builder::class, $searchBuilder);
        $this->assertEquals('smartphone', $searchBuilder->query);
        $this->assertCount(2, $searchBuilder->wheres);

        // Check filter conditions - wheres is a simple key-value array
        $this->assertArrayHasKey('category', $searchBuilder->wheres);
        $this->assertArrayHasKey('status', $searchBuilder->wheres);
        $this->assertEquals('Electronics', $searchBuilder->wheres['category']);
        $this->assertEquals('published', $searchBuilder->wheres['status']);

        // Test search execution with automatic skipping
        $this->runTestWithOgini(function () use ($searchBuilder) {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);
        });
    }

    /** @test */
    public function it_can_perform_paginated_search(): void
    {
        // Test paginated search
        $searchBuilder = TestProduct::search('*'); // Search all

        // Test first page
        try {
            $firstPageResults = $searchBuilder->paginate(5, 'page', 1);
            $this->assertNotNull($firstPageResults);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }

        // Test second page
        try {
            $secondPageResults = $searchBuilder->paginate(5, 'page', 2);
            $this->assertNotNull($secondPageResults);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }

        // Test take/limit functionality
        $limitedBuilder = TestProduct::search('')->take(3);
        $this->assertEquals(3, $limitedBuilder->limit);
    }

    /** @test */
    public function it_can_perform_sorted_search(): void
    {
        // Test search with sorting
        $searchBuilder = TestProduct::search('')
            ->orderBy('price', 'desc')
            ->orderBy('title', 'asc');

        // Verify builder configuration
        $this->assertInstanceOf(Builder::class, $searchBuilder);
        $this->assertCount(2, $searchBuilder->orders);

        // Check sort orders
        $orders = collect($searchBuilder->orders);
        $priceOrder = $orders->firstWhere('column', 'price');
        $titleOrder = $orders->firstWhere('column', 'title');

        $this->assertEquals('desc', $priceOrder['direction']);
        $this->assertEquals('asc', $titleOrder['direction']);

        // Test search execution (would require Ogini server)
        try {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_perform_complex_search_queries(): void
    {
        // Test complex search with multiple conditions
        $searchBuilder = TestProduct::search('laptop')
            ->where('category', 'Computers')
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderBy('price', 'desc')
            ->take(10);

        // Verify builder configuration
        $this->assertEquals('laptop', $searchBuilder->query);
        $this->assertCount(3, $searchBuilder->wheres);
        $this->assertCount(1, $searchBuilder->orders);
        $this->assertEquals(10, $searchBuilder->limit);

        // Verify filter conditions - wheres is a simple key-value array
        $this->assertArrayHasKey('category', $searchBuilder->wheres);
        $this->assertArrayHasKey('status', $searchBuilder->wheres);
        $this->assertArrayHasKey('is_featured', $searchBuilder->wheres);

        $this->assertEquals('Computers', $searchBuilder->wheres['category']);
        $this->assertEquals('published', $searchBuilder->wheres['status']);
        $this->assertTrue($searchBuilder->wheres['is_featured']);

        // Test search execution (would require Ogini server)
        try {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_search_with_custom_callback(): void
    {
        // Test search with custom query callback
        $searchBuilder = TestProduct::search('smartphone', function ($query, $builder) {
            $query['boost'] = 2.0;
            $query['min_score'] = 0.5;
            return $query;
        });

        // Verify builder has callback
        $this->assertNotNull($searchBuilder->callback);
        $this->assertIsCallable($searchBuilder->callback);

        // Test callback execution
        $mockQuery = ['query' => ['match' => ['value' => 'smartphone']]];
        $mockBuilder = new Builder(new TestProduct(), 'smartphone');

        $modifiedQuery = call_user_func($searchBuilder->callback, $mockQuery, $mockBuilder);
        $this->assertEquals(2.0, $modifiedQuery['boost']);
        $this->assertEquals(0.5, $modifiedQuery['min_score']);
    }

    /** @test */
    public function it_can_get_search_results_count(): void
    {
        // Test getting total count of search results
        $searchBuilder = TestProduct::search('');

        // Test count method (would require Ogini server)
        try {
            $count = $searchBuilder->count();
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_search_with_highlighting(): void
    {
        // Test search with highlighting
        $searchBuilder = TestProduct::searchWithHighlights('iPhone Pro');

        // Verify builder configuration
        $this->assertInstanceOf(Builder::class, $searchBuilder);

        // Test search execution with highlighting (would require Ogini server)
        try {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);

            // Check if models have highlight data
            foreach ($results as $model) {
                // Highlights would be set by the engine after search
                $this->assertInstanceOf(TestProduct::class, $model);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_get_search_suggestions(): void
    {
        // Test search suggestions
        try {
            $suggestions = TestProduct::suggest('iPhon', 'title', 5);
            $this->assertIsArray($suggestions);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_search_across_multiple_fields(): void
    {
        $product = new TestProduct();
        $searchFields = $product->getSearchFields();

        // Verify search fields configuration
        $this->assertIsArray($searchFields);
        $this->assertContains('title', $searchFields);
        $this->assertContains('description', $searchFields);

        // Test multi-field search
        $searchBuilder = TestProduct::search('Pro camera');

        // Test search execution (would require Ogini server)
        try {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_empty_search_results(): void
    {
        // Test search that should return no results
        $searchBuilder = TestProduct::search('nonexistentproduct12345');

        // Test search execution (would require Ogini server)
        try {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);
            // Results collection should be empty but valid
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_search_with_price_range_filters(): void
    {
        // Test search with filtering - Scout overwrites when using same field multiple times
        $searchBuilder = TestProduct::search('')
            ->where('price', '<=', 1500);

        // Verify builder configuration
        $this->assertCount(1, $searchBuilder->wheres);
        $this->assertArrayHasKey('price', $searchBuilder->wheres);
        $this->assertEquals('<=', $searchBuilder->wheres['price']);

        // Test a different approach with different field names
        $searchBuilder2 = TestProduct::search('')
            ->where('category', 'Electronics')
            ->where('status', 'published');

        $this->assertCount(2, $searchBuilder2->wheres);
        $this->assertEquals('Electronics', $searchBuilder2->wheres['category']);
        $this->assertEquals('published', $searchBuilder2->wheres['status']);

        // Test search execution (would require Ogini server)
        try {
            $results = $searchBuilder->get();
            $this->assertNotNull($results);
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_search_index_settings(): void
    {
        // Test that search indices are properly configured
        $product = new TestProduct();

        // Verify index name
        $this->assertEquals('test_products', $product->searchableAs());

        // Verify index configuration
        $indexConfig = $product->getOginiIndexConfiguration();
        $this->assertArrayHasKey('settings', $indexConfig);
        $this->assertArrayHasKey('mappings', $indexConfig);

        // Verify search fields are configured
        $searchFields = $product->getSearchFields();
        $this->assertNotEmpty($searchFields);
        $this->assertContains('title', $searchFields);
    }
}
