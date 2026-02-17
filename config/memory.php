<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    */

    'embedding_provider' => env('MEMORY_EMBEDDING_PROVIDER', 'openai'),
    'embedding_model' => env('MEMORY_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => 1536,

    /*
    |--------------------------------------------------------------------------
    | Search Settings
    |--------------------------------------------------------------------------
    */

    'search' => [
        'default_limit' => 10,
        'vector_weight' => 0.7,
        'keyword_weight' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-indexing
    |--------------------------------------------------------------------------
    */

    'auto_index' => [
        'enabled' => true,
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
