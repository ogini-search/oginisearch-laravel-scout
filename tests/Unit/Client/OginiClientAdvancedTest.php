<?php

namespace OginiScoutDriver\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Exceptions\OginiException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;

class OginiClientAdvancedTest extends TestCase
{
    protected OginiClient $client;
    protected $mockHttpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHttpClient = Mockery::mock(Client::class);
        $this->client = new OginiClient('http://localhost:3000', 'test-api-key');
        $this->client->setHttpClient($this->mockHttpClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_query_suggestions(): void
    {
        $indexName = 'test_index';
        $text = 'search';
        $expectedResponse = [
            'suggestions' => [
                ['text' => 'search engine', 'score' => 0.95],
                ['text' => 'search results', 'score' => 0.87],
            ]
        ];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('POST', "/api/indices/{$indexName}/suggestions", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->getQuerySuggestions($indexName, $text, ['size' => 5]);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_autocomplete_suggestions(): void
    {
        $indexName = 'test_index';
        $prefix = 'sear';
        $expectedResponse = [
            'completions' => [
                'search',
                'searching',
                'searcher',
            ]
        ];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('POST', "/api/indices/{$indexName}/autocomplete", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->getAutocompleteSuggestions($indexName, $prefix);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_add_synonyms(): void
    {
        $indexName = 'test_index';
        $synonyms = [
            ['car', 'automobile', 'vehicle'],
            ['fast', 'quick', 'rapid'],
        ];
        $expectedResponse = ['success' => true, 'message' => 'Synonyms added'];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('POST', "/api/indices/{$indexName}/synonyms", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->addSynonyms($indexName, $synonyms);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_synonyms(): void
    {
        $indexName = 'test_index';
        $expectedResponse = [
            'synonyms' => [
                ['car', 'automobile', 'vehicle'],
                ['fast', 'quick', 'rapid'],
            ]
        ];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('GET', "/api/indices/{$indexName}/synonyms", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->getSynonyms($indexName);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_update_synonyms(): void
    {
        $indexName = 'test_index';
        $synonyms = [
            ['updated', 'modified', 'changed'],
        ];
        $expectedResponse = ['success' => true, 'message' => 'Synonyms updated'];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('PUT', "/api/indices/{$indexName}/synonyms", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->updateSynonyms($indexName, $synonyms);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_delete_synonyms(): void
    {
        $indexName = 'test_index';
        $expectedResponse = ['success' => true, 'message' => 'Synonyms deleted'];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('DELETE', "/api/indices/{$indexName}/synonyms", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->deleteSynonyms($indexName);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_configure_stopwords(): void
    {
        $indexName = 'test_index';
        $stopwords = ['the', 'a', 'an', 'and', 'or'];
        $language = 'en';
        $expectedResponse = ['success' => true, 'message' => 'Stopwords configured'];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('POST', "/api/indices/{$indexName}/stopwords", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->configureStopwords($indexName, $stopwords, $language);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_stopwords(): void
    {
        $indexName = 'test_index';
        $expectedResponse = [
            'stopwords' => ['the', 'a', 'an', 'and', 'or'],
            'language' => 'en'
        ];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('GET', "/api/indices/{$indexName}/stopwords", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->getStopwords($indexName);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_update_stopwords(): void
    {
        $indexName = 'test_index';
        $stopwords = ['the', 'a', 'an'];
        $expectedResponse = ['success' => true, 'message' => 'Stopwords updated'];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('PUT', "/api/indices/{$indexName}/stopwords", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->updateStopwords($indexName, $stopwords);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_reset_stopwords(): void
    {
        $indexName = 'test_index';
        $expectedResponse = ['success' => true, 'message' => 'Stopwords reset to default'];

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->with('DELETE', "/api/indices/{$indexName}/stopwords", Mockery::any())
            ->andReturn(new Response(200, [], json_encode($expectedResponse)));

        $result = $this->client->resetStopwords($indexName);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_handles_errors_in_advanced_methods(): void
    {
        $indexName = 'test_index';

        $this->mockHttpClient->shouldReceive('request')
            ->once()
            ->andReturn(new Response(500, [], json_encode(['error' => 'Server error'])));

        $this->expectException(OginiException::class);

        $this->client->getQuerySuggestions($indexName, 'test');
    }
}
