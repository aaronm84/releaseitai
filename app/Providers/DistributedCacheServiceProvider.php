<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Cache\DistributedCacheManager;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Cache\CacheMonitoringService;
use App\Observers\WorkstreamCacheObserver;
use App\Observers\FeedbackCacheObserver;
use App\Observers\EmbeddingCacheObserver;
use App\Observers\WorkstreamPermissionCacheObserver;
use App\Models\Workstream;
use App\Models\Feedback;
use App\Models\Embedding;
use App\Models\WorkstreamPermission;

/**
 * DistributedCacheServiceProvider - Registers distributed caching services and observers
 *
 * This service provider bootstraps the distributed caching system, registering
 * all cache services and setting up model observers for automatic cache invalidation.
 */
class DistributedCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the distributed cache manager as a singleton
        $this->app->singleton(DistributedCacheManager::class, function ($app) {
            return new DistributedCacheManager();
        });

        // Register cache invalidation service
        $this->app->singleton(CacheInvalidationService::class, function ($app) {
            return new CacheInvalidationService($app->make(DistributedCacheManager::class));
        });

        // Register cache monitoring service
        $this->app->singleton(CacheMonitoringService::class, function ($app) {
            return new CacheMonitoringService($app->make(DistributedCacheManager::class));
        });

        // Register cache manager alias for easier access
        $this->app->alias(DistributedCacheManager::class, 'distributed-cache');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register model observers for automatic cache invalidation
        $this->registerModelObservers();

        // Publish configuration files
        $this->publishes([
            __DIR__ . '/../../config/cache_distributed.php' => config_path('cache_distributed.php'),
        ], 'distributed-cache-config');

        // Register artisan commands (commented out for now - commands not yet created)
        // if ($this->app->runningInConsole()) {
        //     $this->commands([
        //         \App\Console\Commands\Cache\WarmDistributedCache::class,
        //         \App\Console\Commands\Cache\FlushDistributedCache::class,
        //         \App\Console\Commands\Cache\MonitorCacheHealth::class,
        //         \App\Console\Commands\Cache\AnalyzeCachePerformance::class,
        //     ]);
        // }

        // Set up cache configuration for Redis
        $this->configureCacheStores();

        // Register event listeners for cache warming
        $this->registerEventListeners();
    }

    /**
     * Register model observers for automatic cache invalidation
     */
    private function registerModelObservers(): void
    {
        // Only register observers if distributed caching is enabled
        if (config('cache_distributed.enabled', true)) {
            Workstream::observe(WorkstreamCacheObserver::class);
            Feedback::observe(FeedbackCacheObserver::class);
            Embedding::observe(EmbeddingCacheObserver::class);
            WorkstreamPermission::observe(WorkstreamPermissionCacheObserver::class);
        }
    }

    /**
     * Configure cache stores for distributed caching
     */
    private function configureCacheStores(): void
    {
        // Extend cache configuration with distributed cache settings
        $cacheConfig = config('cache.stores.redis', []);

        // Set up distributed cache store if not already configured
        if (!isset($cacheConfig['options']['prefix'])) {
            config([
                'cache.stores.redis.options.prefix' => 'releaseit_dist_cache:',
            ]);
        }

        // Configure serialization for better performance
        config([
            'cache.stores.redis.options.serializer' => 'php',
            'cache.stores.redis.options.compression' => 'lz4',
        ]);
    }

    /**
     * Register event listeners for cache warming and invalidation
     */
    private function registerEventListeners(): void
    {
        // Listen for application events that might trigger cache warming
        $this->app['events']->listen(
            'Illuminate\Console\Events\CommandFinished',
            function ($event) {
                // Warm cache after certain commands complete
                $warmingCommands = [
                    'migrate',
                    'db:seed',
                    'queue:work'
                ];

                if (in_array($event->command, $warmingCommands)) {
                    $this->triggerCacheWarming();
                }
            }
        );

        // Listen for user login events to warm user-specific caches
        $this->app['events']->listen(
            'Illuminate\Auth\Events\Login',
            function ($event) {
                $this->warmUserSpecificCaches($event->user->id);
            }
        );
    }

    /**
     * Trigger cache warming for frequently accessed data
     */
    private function triggerCacheWarming(): void
    {
        try {
            $cacheManager = $this->app->make(DistributedCacheManager::class);

            // Define cache warming specifications
            $warmingSpecs = [
                // Warm root workstream hierarchies
                [
                    'key' => 'root_workstreams_summary',
                    'callback' => function () {
                        return Workstream::whereNull('parent_workstream_id')
                            ->withBulkEssentials()
                            ->get()
                            ->toArray();
                    },
                    'ttl' => 3600,
                    'tags' => ['workstream_hierarchy', 'root_workstreams']
                ],
                // Warm frequently accessed permissions
                [
                    'key' => 'common_permission_patterns',
                    'callback' => function () {
                        return []; // Placeholder - would implement based on usage patterns
                    },
                    'ttl' => 1800,
                    'tags' => ['permissions', 'common_patterns']
                ]
            ];

            $cacheManager->warm($warmingSpecs);

        } catch (\Exception $e) {
            // Log error but don't fail the application
            \Log::warning('Cache warming failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Warm user-specific caches when user logs in
     *
     * @param int $userId
     */
    private function warmUserSpecificCaches(int $userId): void
    {
        try {
            $cacheManager = $this->app->make(DistributedCacheManager::class);

            // Warm user's workstream permissions
            $userWorkstreams = Workstream::whereHas('permissions', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->limit(10)->get();

            $warmingSpecs = [];
            foreach ($userWorkstreams as $workstream) {
                $warmingSpecs[] = [
                    'key' => "workstream:permissions:{$workstream->id}:{$userId}",
                    'callback' => function () use ($workstream, $userId) {
                        return $workstream->getEffectivePermissionsForUserOptimized($userId);
                    },
                    'ttl' => 1800,
                    'tags' => ["user_permissions:{$userId}", "workstream_permissions:{$workstream->id}"]
                ];
            }

            if (!empty($warmingSpecs)) {
                $cacheManager->warm($warmingSpecs);
            }

        } catch (\Exception $e) {
            \Log::warning('User-specific cache warming failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            DistributedCacheManager::class,
            CacheInvalidationService::class,
            CacheMonitoringService::class,
            'distributed-cache',
        ];
    }
}