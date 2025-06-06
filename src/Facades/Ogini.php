<?php

namespace OginiScoutDriver\Facades;

use Illuminate\Support\Facades\Facade;
use OginiScoutDriver\Client\OginiClient;

/**
 * @method static array createIndex(string $indexName, array $configuration = [])
 * @method static array getIndex(string $indexName)
 * @method static array deleteIndex(string $indexName)
 * @method static array listIndices(?string $status = null)
 * @method static array updateIndexSettings(string $indexName, array $settings)
 * @method static array indexDocument(string $indexName, array $document, ?string $documentId = null)
 * @method static array getDocument(string $indexName, string $documentId)
 * @method static array updateDocument(string $indexName, string $documentId, array $document)
 * @method static array deleteDocument(string $indexName, string $documentId)
 * @method static array bulkIndexDocuments(string $indexName, array $documents)
 * @method static array deleteByQuery(string $indexName, array $query)
 * @method static array listDocuments(string $indexName, int $limit = 10, int $offset = 0, ?string $filter = null)
 * @method static array search(string $indexName, array $searchQuery, ?int $size = null, ?int $from = null)
 * @method static array suggest(string $indexName, string $text, ?string $field = null, ?int $size = null)
 * @method static array getQuerySuggestions(string $indexName, string $text, array $options = [])
 * @method static array getAutocompleteSuggestions(string $indexName, string $prefix, array $options = [])
 * @method static array addSynonyms(string $indexName, array $synonyms)
 * @method static array getSynonyms(string $indexName)
 * @method static array updateSynonyms(string $indexName, array $synonyms)
 * @method static array deleteSynonyms(string $indexName, array $synonymGroups = [])
 * @method static array configureStopwords(string $indexName, array $stopwords, ?string $language = null)
 * @method static array getStopwords(string $indexName)
 * @method static array updateStopwords(string $indexName, array $stopwords, ?string $language = null)
 * @method static array resetStopwords(string $indexName, ?string $language = null)
 * @method static string getBaseUrl()
 * @method static string getApiKey()
 * @method static array getConfig()
 * @method static void setHttpClient(\GuzzleHttp\Client $client)
 */
class Ogini extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return OginiClient::class;
    }
}
