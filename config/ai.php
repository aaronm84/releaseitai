<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by your
    | application. This provider will be used when no provider is explicitly
    | specified when making AI requests.
    |
    */

    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider API Keys
    |--------------------------------------------------------------------------
    |
    | Here you should specify the API keys for each AI provider you wish to
    | use. You should store these in your environment file and reference
    | them here for security.
    |
    */

    'openai_api_key' => env('OPENAI_API_KEY'),
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Cost Limits
    |--------------------------------------------------------------------------
    |
    | These options allow you to set daily and monthly cost limits for AI
    | requests to prevent unexpected billing. Set to null to disable limits.
    |
    */

    'cost_limit_daily' => env('AI_COST_LIMIT_DAILY', 50.00),
    'cost_limit_monthly' => env('AI_COST_LIMIT_MONTHLY', 1000.00),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | This option controls how many AI requests can be made per minute to
    | prevent hitting provider rate limits and manage costs.
    |
    */

    'rate_limit_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum time in seconds to wait for AI provider responses.
    |
    */

    'timeout' => env('AI_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for AI responses to reduce costs and improve
    | performance for repeated requests.
    |
    */

    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'ai_response:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for AI requests and responses.
    |
    */

    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'log_prompts' => env('AI_LOG_PROMPTS', false), // Set to false in production for privacy
        'log_responses' => env('AI_LOG_RESPONSES', false), // Set to false in production for privacy
    ],

];