<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    */

    'embedding_provider' => env('MEMORY_EMBEDDING_PROVIDER', 'ollama'),
    'embedding_model' => env('MEMORY_EMBEDDING_MODEL', 'nomic-embed-text'),
    'embedding_dimensions' => (int) env('MEMORY_EMBEDDING_DIMENSIONS', 768),
    'ollama_host' => env('OLLAMA_HOST', 'http://192.168.2.130:11434'),

    /*
    |--------------------------------------------------------------------------
    | Embedding Generation
    |--------------------------------------------------------------------------
    */

    'embedding' => [
        'timeout' => (int) env('MEMORY_EMBEDDING_TIMEOUT', 30),
        'max_content_length' => (int) env('MEMORY_MAX_CONTENT_LENGTH', 8000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Settings
    |--------------------------------------------------------------------------
    */

    'search' => [
        'enabled' => (bool) env('MEMORY_SEARCH_ENABLED', true),
        'default_limit' => 10,
        'vector_weight' => 0.7,
        'keyword_weight' => 0.3,
        'rrf_k' => 60,
        'candidate_multiplier' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-indexing
    |--------------------------------------------------------------------------
    */

    'auto_index' => [
        'enabled' => (bool) env('MEMORY_AUTO_INDEX_ENABLED', true),
        'debounce_seconds' => 1.5,
        'watch_paths' => ['memory/', 'MEMORY.md'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Indexing
    |--------------------------------------------------------------------------
    */

    'index_sessions' => env('MEMORY_INDEX_SESSIONS', true),

];
