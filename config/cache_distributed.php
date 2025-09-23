<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Distributed Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the distributed caching system that replaces in-memory
    | caching patterns with Redis-based distributed caching for scalability.
    |
    */

    'default_store' => env('DISTRIBUTED_CACHE_STORE', 'redis'),

    'default_ttl' => env('DISTRIBUTED_CACHE_TTL', 3600), // 1 hour

    'redis_connection' => env('DISTRIBUTED_CACHE_REDIS_CONNECTION', 'cache'),

    'store_metadata' => env('DISTRIBUTED_CACHE_STORE_METADATA', true),

    'compression_threshold' => env('DISTRIBUTED_CACHE_COMPRESSION_THRESHOLD', 1024), // bytes

    'compression_level' => env('DISTRIBUTED_CACHE_COMPRESSION_LEVEL', 6), // 1-9

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Presets
    |--------------------------------------------------------------------------
    |
    | Predefined TTL values for different types of cached data
    |
    */
    'ttl_presets' => [
        'short' => 300,    // 5 minutes - for rapidly changing data
        'medium' => 1800,  // 30 minutes - for moderately stable data
        'long' => 3600,    // 1 hour - for stable data
        'very_long' => 21600, // 6 hours - for very stable data
        'daily' => 86400,  // 24 hours - for daily aggregations
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Tags Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cache tagging and invalidation patterns
    |
    */
    'tags' => [
        'workstream_hierarchy' => [
            'ttl' => 'long',
            'invalidation_triggers' => [
                'workstream_updated',
                'workstream_moved',
                'workstream_deleted'
            ]
        ],
        'user_permissions' => [
            'ttl' => 'medium',
            'invalidation_triggers' => [
                'permission_granted',
                'permission_revoked',
                'workstream_hierarchy_changed'
            ]
        ],
        'feedback_patterns' => [
            'ttl' => 'long',
            'invalidation_triggers' => [
                'feedback_created',
                'feedback_batch_processed'
            ]
        ],
        'rag_similarity' => [
            'ttl' => 'medium',
            'invalidation_triggers' => [
                'embedding_updated',
                'feedback_created'
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cache warming strategies
    |
    */
    'warming' => [
        'enabled' => env('DISTRIBUTED_CACHE_WARMING_ENABLED', true),
        'batch_size' => env('DISTRIBUTED_CACHE_WARMING_BATCH_SIZE', 50),
        'max_execution_time' => env('DISTRIBUTED_CACHE_WARMING_MAX_TIME', 30), // seconds
        'strategies' => [
            'eager' => [
                'workstream_hierarchies' => true,
                'user_permissions' => true,
                'frequently_accessed_data' => true
            ],
            'lazy' => [
                'feedback_patterns' => true,
                'rag_similarity' => true
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Metrics
    |--------------------------------------------------------------------------
    |
    | Configuration for cache monitoring and performance metrics
    |
    */
    'monitoring' => [
        'enabled' => env('DISTRIBUTED_CACHE_MONITORING_ENABLED', true),
        'metrics_retention' => env('DISTRIBUTED_CACHE_METRICS_RETENTION', 86400), // 24 hours
        'slow_query_threshold' => env('DISTRIBUTED_CACHE_SLOW_THRESHOLD', 100), // milliseconds
        'alert_thresholds' => [
            'hit_ratio_min' => 80, // percent
            'error_rate_max' => 5, // percent
            'response_time_max' => 50 // milliseconds
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Lock Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for distributed locking to prevent cache stampede
    |
    */
    'locks' => [
        'default_ttl' => env('DISTRIBUTED_CACHE_LOCK_TTL', 30), // seconds
        'max_wait_time' => env('DISTRIBUTED_CACHE_LOCK_MAX_WAIT', 5), // seconds
        'retry_interval' => env('DISTRIBUTED_CACHE_LOCK_RETRY_INTERVAL', 100), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Patterns
    |--------------------------------------------------------------------------
    |
    | Standardized patterns for cache keys to ensure consistency
    |
    */
    'key_patterns' => [
        'workstream_descendants' => 'workstream:descendants:{id}',
        'workstream_ancestors' => 'workstream:ancestors:{id}',
        'workstream_tree' => 'workstream:tree:{id}',
        'workstream_rollup' => 'workstream:rollup:{id}',
        'workstream_permissions' => 'workstream:permissions:{workstream_id}:{user_id}',
        'user_permissions_check' => 'user:permissions:{workstream_id}:{user_id}:{type}',
        'similar_feedback' => 'feedback:similar:{hash}',
        'user_feedback_patterns' => 'user:feedback_patterns:{user_id}',
        'user_preferences' => 'user:preferences:{user_id}',
        'rate_limit' => 'rate_limit:{type}:{user_id}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for graceful degradation when cache is unavailable
    |
    */
    'fallback' => [
        'enabled' => env('DISTRIBUTED_CACHE_FALLBACK_ENABLED', true),
        'local_cache_enabled' => env('DISTRIBUTED_CACHE_LOCAL_FALLBACK', true),
        'local_cache_ttl' => env('DISTRIBUTED_CACHE_LOCAL_TTL', 300), // 5 minutes
        'max_retries' => env('DISTRIBUTED_CACHE_MAX_RETRIES', 3),
        'retry_delay' => env('DISTRIBUTED_CACHE_RETRY_DELAY', 1000), // milliseconds
    ]
];