<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OginiSearch API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your OginiSearch instance settings. You will need
    | to provide your API endpoint and authentication key to connect to the
    | OginiSearch service.
    |
    */

    'base_url' => env('OGINI_BASE_URL', 'http://localhost:3000'),

    'api_key' => env('OGINI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Auto Create Index
    |--------------------------------------------------------------------------
    |
    | When enabled, indices will be automatically created when the first model
    | using the OginiSearchable trait is created. This is convenient for
    | development but you may want to disable it in production.
    |
    */

    'auto_create_index' => env('OGINI_AUTO_CREATE_INDEX', false),

    /*
    |--------------------------------------------------------------------------
    | Client Configuration
    |--------------------------------------------------------------------------
    |
    | These options are passed to the HTTP client. You can configure timeouts,
    | retries, and other HTTP client specific options here.
    |
    */

    'client' => [
        'timeout' => env('OGINI_TIMEOUT', 30),
        'retry_attempts' => env('OGINI_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('OGINI_RETRY_DELAY', 100), // milliseconds
        'bulk_timeout' => env('OGINI_BULK_TIMEOUT', 600), // 10 minutes for bulk operations
    ],

    /*
    |--------------------------------------------------------------------------
    | Engine Configuration
    |--------------------------------------------------------------------------
    |
    | These options control how the Scout engine behaves. You can configure
    | features like soft deletes, search behavior, and more.
    |
    */

    'engine' => [
        'soft_delete' => env('OGINI_SOFT_DELETE', false),
        'batch_size' => env('OGINI_BATCH_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control performance optimizations including caching,
    | batch processing, and connection pooling.
    |
    */

    'performance' => [
        // Caching settings
        'cache' => [
            'enabled' => env('OGINI_CACHE_ENABLED', true),
            'driver' => env('OGINI_CACHE_DRIVER', 'redis'),
            'query_ttl' => env('OGINI_CACHE_QUERY_TTL', 300), // 5 minutes
            'suggestion_ttl' => env('OGINI_CACHE_SUGGESTION_TTL', 1800), // 30 minutes
            'facet_ttl' => env('OGINI_CACHE_FACET_TTL', 600), // 10 minutes
            'prefix' => env('OGINI_CACHE_PREFIX', 'ogini_search'),
        ],

        // Batch processing settings
        'batch' => [
            'enabled' => env('OGINI_BATCH_ENABLED', true),
            'batch_size' => env('OGINI_BATCH_SIZE', 500),
            'timeout' => env('OGINI_BATCH_TIMEOUT', 600), // 10 minutes for batch operations
            'retry_attempts' => env('OGINI_BATCH_RETRY_ATTEMPTS', 3),
            'delay_between_batches' => env('OGINI_BATCH_DELAY', 100), // milliseconds
        ],

        // Queue processing settings
        'queue' => [
            'connection' => env('OGINI_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
            'queue_name' => env('OGINI_QUEUE_NAME', 'ogini-bulk'),
            'timeout' => env('OGINI_QUEUE_TIMEOUT', 600),
            'retry_times' => env('OGINI_QUEUE_RETRY_TIMES', 3),
        ],

        // Connection pool settings
        'connection_pool' => [
            'enabled' => env('OGINI_POOL_ENABLED', true),
            'pool_size' => env('OGINI_POOL_SIZE', 5),
            'connection_timeout' => env('OGINI_POOL_CONNECTION_TIMEOUT', 10),
            'request_timeout' => env('OGINI_POOL_REQUEST_TIMEOUT', 30),
            'keep_alive_timeout' => env('OGINI_POOL_KEEP_ALIVE_TIMEOUT', 60),
            'max_idle_time' => env('OGINI_POOL_MAX_IDLE_TIME', 300),
            'enable_connection_reuse' => env('OGINI_POOL_REUSE_ENABLED', true),
        ],

        // Query optimization settings
        'query_optimization' => [
            'enabled' => env('OGINI_QUERY_OPTIMIZATION_ENABLED', true),
            'enable_query_rewriting' => env('OGINI_QUERY_REWRITING_ENABLED', true),
            'enable_field_optimization' => env('OGINI_FIELD_OPTIMIZATION_ENABLED', true),
            'enable_filter_optimization' => env('OGINI_FILTER_OPTIMIZATION_ENABLED', true),
            'max_query_length' => env('OGINI_MAX_QUERY_LENGTH', 1000),
            'enable_wildcard_optimization' => env('OGINI_WILDCARD_OPTIMIZATION_ENABLED', true),
            'enable_phrase_detection' => env('OGINI_PHRASE_DETECTION_ENABLED', true),
            'boost_exact_matches' => env('OGINI_BOOST_EXACT_MATCHES', true),
            'min_term_length' => env('OGINI_MIN_TERM_LENGTH', 3),
            'max_complexity_score' => env('OGINI_MAX_COMPLEXITY_SCORE', 15),
            'performance_check_threshold' => env('OGINI_PERFORMANCE_CHECK_THRESHOLD', 100),
            'wildcard_penalty' => env('OGINI_WILDCARD_PENALTY', 5),
            'phrase_boost' => env('OGINI_PHRASE_BOOST', 1.5),
            'exact_match_boost' => env('OGINI_EXACT_MATCH_BOOST', 2.0),
            'fuzzy_match_boost' => env('OGINI_FUZZY_MATCH_BOOST', 1.0),
            'retry_delay' => env('OGINI_RETRY_DELAY', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Configuration Defaults
    |--------------------------------------------------------------------------
    |
    | Default configuration for new indexes. These can be overridden on a
    | per-model basis using the getOginiIndexConfiguration method.
    |
    */

    'index_defaults' => [
        'typo_tolerance' => [
            'enabled' => true,
            'min_word_size_for_typos' => [
                'one_typo' => 5,
                'two_typos' => 8,
            ],
        ],
        'pagination' => [
            'max_total_hits' => 10000,
        ],
    ],
];
