<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CSV Import Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for NYC 311 CSV data import processing
    |
    */

    'fathom_site_id' => env('FATHOM_SITE_ID', 'not-set'),

    'csv_batch_size' => env('LARACITY_CSV_BATCH_SIZE', 1000),

    'timezone' => env('LARACITY_TIMEZONE', 'America/Toronto'),

    /*
    |--------------------------------------------------------------------------
    | AI Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for AI-powered complaint analysis and risk assessment
    |
    */

    'escalate_threshold' => env('COMPLAINT_ESCALATE_THRESHOLD', 0.7),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenAI API integration
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Database Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for RAG system and vector embeddings
    |
    */

    'embeddings' => [
        'dimension' => env('EMBEDDING_DIMENSION', 1536),
    ],

    'vector_search' => [
        'k' => env('VECTOR_SEARCH_K', 5),
        'similarity_threshold' => env('SIMILARITY_THRESHOLD', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Retrieval Augmented Generation system
    |
    */

    'rag' => [
        'chunk_size' => env('RAG_CHUNK_SIZE', 1000),
        'chunk_overlap' => env('RAG_CHUNK_OVERLAP', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for escalation notifications
    |
    */

    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],
];
