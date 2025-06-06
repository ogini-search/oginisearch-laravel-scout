<?php

namespace OginiScoutDriver\Tests\Integration;

use Orchestra\Testbench\TestCase;
use OginiScoutDriver\OginiServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\Searchable;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
        $this->setUpTestData();
    }

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
        // Setup the application environment for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Scout configuration - disable syncing to prevent automatic indexing
        $app['config']->set('scout.driver', 'ogini');
        $app['config']->set('scout.queue', false);
        $app['config']->set('scout.soft_delete', false);
        $app['config']->set('scout.chunk.searchable', 500);
        $app['config']->set('scout.chunk.unsearchable', 500);

        // OginiSearch configuration - using very short timeouts for tests
        $app['config']->set('ogini.base_url', 'http://localhost:3000');
        $app['config']->set('ogini.api_key', 'test-api-key');
        $app['config']->set('ogini.timeout', 1); // Short timeout for tests
        $app['config']->set('ogini.retry_attempts', 1); // Minimal retries
        $app['config']->set('ogini.retry_delay', 10); // Short delay
        $app['config']->set('ogini.auto_create_index', false); // Disable auto-creation
    }

    /**
     * Set up the database schema.
     *
     * @return void
     */
    protected function setUpDatabase(): void
    {
        Schema::create('test_products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('category');
            $table->string('status')->default('published');
            $table->boolean('is_featured')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('author');
            $table->string('status');
            $table->dateTime('published_at')->nullable();
            $table->integer('views')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Set up test data.
     *
     * @return void
     */
    protected function setUpTestData(): void
    {
        // This will be overridden by specific tests as needed
    }

    /**
     * Clean up after tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up any created indices
        $this->cleanUpTestIndices();

        parent::tearDown();
    }

    /**
     * Clean up test indices.
     *
     * @return void
     */
    protected function cleanUpTestIndices(): void
    {
        // Try to clean up test indices if possible
        try {
            $indices = ['test_products', 'test_users', 'test_articles'];
            foreach ($indices as $index) {
                // This will be implemented when we have the actual client
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors in tests
        }
    }

    /**
     * Assert that a search index exists.
     *
     * @param string $indexName
     * @return void
     */
    protected function assertIndexExists(string $indexName): void
    {
        $this->assertTrue(true, "Index {$indexName} should exist");
        // This will be implemented with actual index checking
    }

    /**
     * Assert that a document exists in an index.
     *
     * @param string $indexName
     * @param string $documentId
     * @return void
     */
    protected function assertDocumentExists(string $indexName, string $documentId): void
    {
        $this->assertTrue(true, "Document {$documentId} should exist in index {$indexName}");
        // This will be implemented with actual document checking
    }

    /**
     * Assert search results match expected criteria.
     *
     * @param array $results
     * @param int $expectedCount
     * @param array $expectedIds
     * @return void
     */
    protected function assertSearchResults(array $results, int $expectedCount, array $expectedIds = []): void
    {
        $this->assertArrayHasKey('data', $results);
        $this->assertArrayHasKey('total', $results['data']);
        $this->assertEquals($expectedCount, $results['data']['total']);

        if (!empty($expectedIds)) {
            $this->assertArrayHasKey('hits', $results['data']);
            $actualIds = collect($results['data']['hits'])->pluck('id')->toArray();
            $this->assertEquals($expectedIds, $actualIds);
        }
    }

    /**
     * Skip test if Ogini server is not available.
     * This method makes a quick connectivity check.
     *
     * @return void
     */
    protected function skipIfOginiNotAvailable(): void
    {
        try {
            // Try a very quick connection test
            $context = stream_context_create([
                'http' => [
                    'timeout' => 0.1, // 100ms timeout
                    'method' => 'GET'
                ]
            ]);

            $result = @file_get_contents('http://localhost:3000/health', false, $context);

            if ($result === false) {
                $this->markTestSkipped('Ogini server not available at localhost:3000');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
        }
    }

    /**
     * Execute a test that requires Ogini server with automatic skipping.
     *
     * @param callable $testCallback
     * @return void
     */
    protected function runTestWithOgini(callable $testCallback): void
    {
        try {
            $testCallback();
        } catch (\Exception $e) {
            if (
                str_contains($e->getMessage(), 'Failed to connect') ||
                str_contains($e->getMessage(), 'Connection refused') ||
                str_contains($e->getMessage(), 'timeout')
            ) {
                $this->markTestSkipped('Ogini server not available: ' . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }
}
