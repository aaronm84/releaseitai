<?php

namespace App\Services\Cache;

use App\Services\Cache\DistributedCacheManager;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CacheMonitoringService - Provides monitoring and alerting for distributed cache health
 *
 * This service monitors cache performance, tracks metrics, and provides alerts
 * when cache performance degrades or errors occur.
 */
class CacheMonitoringService
{
    private DistributedCacheManager $cacheManager;
    private array $config;
    private array $metrics = [];

    public function __construct(DistributedCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
        $this->config = config('cache_distributed.monitoring', []);
    }

    /**
     * Get comprehensive cache health report
     *
     * @return array
     */
    public function getCacheHealthReport(): array
    {
        $startTime = microtime(true);

        try {
            $health = $this->cacheManager->getHealthMetrics();
            $redisInfo = $this->getRedisHealthInfo();
            $performanceMetrics = $this->getPerformanceMetrics();
            $alerts = $this->checkAlertThresholds($health, $performanceMetrics);

            $report = [
                'timestamp' => Carbon::now()->toISOString(),
                'overall_status' => $this->calculateOverallStatus($health, $alerts),
                'cache_health' => $health,
                'redis_health' => $redisInfo,
                'performance_metrics' => $performanceMetrics,
                'alerts' => $alerts,
                'report_generation_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];

            // Store report for trend analysis
            $this->storeHealthReport($report);

            return $report;

        } catch (\Exception $e) {
            Log::error('Cache health report generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'timestamp' => Carbon::now()->toISOString(),
                'overall_status' => 'critical',
                'error' => 'Failed to generate health report: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Redis-specific health information
     *
     * @return array
     */
    public function getRedisHealthInfo(): array
    {
        try {
            $redis = Redis::connection($this->config['redis_connection'] ?? 'cache');
            $info = $redis->info();
            $config = $redis->config('get', '*');

            return [
                'connected' => true,
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime_seconds' => $info['uptime_in_seconds'] ?? 0,
                'memory' => [
                    'used_memory' => $info['used_memory'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                    'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                    'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'N/A',
                    'maxmemory' => $config['maxmemory'] ?? 0,
                    'memory_usage_percent' => $this->calculateMemoryUsagePercent($info, $config)
                ],
                'connections' => [
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'max_clients' => $config['maxclients'] ?? 0,
                    'rejected_connections' => $info['rejected_connections'] ?? 0
                ],
                'operations' => [
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                    'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                    'hit_rate_percent' => $this->calculateRedisHitRate($info)
                ],
                'persistence' => [
                    'rdb_last_save_time' => $info['rdb_last_save_time'] ?? 0,
                    'rdb_changes_since_last_save' => $info['rdb_changes_since_last_save'] ?? 0,
                    'aof_enabled' => ($info['aof_enabled'] ?? 0) == 1
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Redis health check failed', ['error' => $e->getMessage()]);
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get performance metrics from the cache manager
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'response_times' => $this->getResponseTimeMetrics(),
            'error_rates' => $this->getErrorRateMetrics(),
            'throughput' => $this->getThroughputMetrics(),
            'cache_efficiency' => $this->getCacheEfficiencyMetrics()
        ];
    }

    /**
     * Check alert thresholds and generate alerts
     *
     * @param array $health
     * @param array $performance
     * @return array
     */
    public function checkAlertThresholds(array $health, array $performance): array
    {
        $alerts = [];
        $thresholds = $this->config['alert_thresholds'] ?? [];

        // Check hit ratio
        if (isset($health['hit_ratio']) && isset($thresholds['hit_ratio_min'])) {
            if ($health['hit_ratio'] < $thresholds['hit_ratio_min']) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'performance',
                    'message' => "Cache hit ratio ({$health['hit_ratio']}%) below threshold ({$thresholds['hit_ratio_min']}%)",
                    'severity' => 'medium',
                    'timestamp' => Carbon::now()->toISOString()
                ];
            }
        }

        // Check error rates
        $errorRate = $this->calculateErrorRate($health);
        if ($errorRate > ($thresholds['error_rate_max'] ?? 5)) {
            $alerts[] = [
                'type' => 'error',
                'category' => 'reliability',
                'message' => "Cache error rate ({$errorRate}%) above threshold ({$thresholds['error_rate_max']}%)",
                'severity' => 'high',
                'timestamp' => Carbon::now()->toISOString()
            ];
        }

        // Check response times
        if (isset($health['avg_read_time']) && isset($thresholds['response_time_max'])) {
            if ($health['avg_read_time'] > $thresholds['response_time_max']) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'performance',
                    'message' => "Cache read time ({$health['avg_read_time']}ms) above threshold ({$thresholds['response_time_max']}ms)",
                    'severity' => 'medium',
                    'timestamp' => Carbon::now()->toISOString()
                ];
            }
        }

        // Check Redis connectivity
        if (!($health['redis_connected'] ?? false)) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'connectivity',
                'message' => 'Redis connection failed',
                'severity' => 'critical',
                'timestamp' => Carbon::now()->toISOString()
            ];
        }

        return $alerts;
    }

    /**
     * Record cache operation metrics
     *
     * @param string $operation
     * @param float $duration
     * @param bool $success
     * @param array $metadata
     */
    public function recordMetric(string $operation, float $duration, bool $success = true, array $metadata = []): void
    {
        if (!($this->config['enabled'] ?? true)) {
            return;
        }

        $metric = [
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'timestamp' => Carbon::now()->toISOString(),
            'metadata' => $metadata
        ];

        // Store in memory for immediate access
        $this->metrics[] = $metric;

        // Store in persistent storage for historical analysis
        $this->persistMetric($metric);

        // Cleanup old metrics to prevent memory leaks
        if (count($this->metrics) > 1000) {
            $this->metrics = array_slice($this->metrics, -500);
        }
    }

    /**
     * Get cache operation trends over time
     *
     * @param array $options
     * @return array
     */
    public function getCacheTrends(array $options = []): array
    {
        $period = $options['period'] ?? 'hour';
        $hours = $options['hours'] ?? 24;

        try {
            $trends = DB::table('cache_metrics')
                ->select([
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as period"),
                    'operation',
                    DB::raw('COUNT(*) as operation_count'),
                    DB::raw('AVG(duration_ms) as avg_duration'),
                    DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count'),
                    DB::raw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as error_count')
                ])
                ->where('created_at', '>=', Carbon::now()->subHours($hours))
                ->groupBy('period', 'operation')
                ->orderBy('period')
                ->get();

            return [
                'period' => $period,
                'hours_analyzed' => $hours,
                'trends' => $trends->groupBy('operation')->toArray()
            ];

        } catch (\Exception $e) {
            Log::error('Cache trends analysis failed', ['error' => $e->getMessage()]);
            return [
                'error' => 'Failed to analyze cache trends: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate cache performance report
     *
     * @param array $options
     * @return array
     */
    public function generatePerformanceReport(array $options = []): array
    {
        $timeframe = $options['timeframe'] ?? '24h';
        $includeRecommendations = $options['include_recommendations'] ?? true;

        try {
            $health = $this->getCacheHealthReport();
            $trends = $this->getCacheTrends(['hours' => $this->parseTimeframe($timeframe)]);

            $report = [
                'report_type' => 'performance',
                'timeframe' => $timeframe,
                'generated_at' => Carbon::now()->toISOString(),
                'summary' => [
                    'overall_health' => $health['overall_status'] ?? 'unknown',
                    'total_operations' => $this->getTotalOperations($trends),
                    'average_response_time' => $this->getAverageResponseTime($trends),
                    'error_rate' => $this->getErrorRate($trends),
                    'hit_ratio' => $health['cache_health']['hit_ratio'] ?? 0
                ],
                'detailed_metrics' => $health,
                'trends' => $trends
            ];

            if ($includeRecommendations) {
                $report['recommendations'] = $this->generateRecommendations($health, $trends);
            }

            return $report;

        } catch (\Exception $e) {
            Log::error('Performance report generation failed', ['error' => $e->getMessage()]);
            return [
                'error' => 'Failed to generate performance report: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get response time metrics
     *
     * @return array
     */
    private function getResponseTimeMetrics(): array
    {
        $recentMetrics = array_slice($this->metrics, -100);
        $readTimes = array_column(array_filter($recentMetrics, fn($m) => $m['operation'] === 'cache_read'), 'duration_ms');
        $writeTimes = array_column(array_filter($recentMetrics, fn($m) => $m['operation'] === 'cache_write'), 'duration_ms');

        return [
            'read' => [
                'count' => count($readTimes),
                'avg' => count($readTimes) > 0 ? round(array_sum($readTimes) / count($readTimes), 2) : 0,
                'min' => count($readTimes) > 0 ? min($readTimes) : 0,
                'max' => count($readTimes) > 0 ? max($readTimes) : 0
            ],
            'write' => [
                'count' => count($writeTimes),
                'avg' => count($writeTimes) > 0 ? round(array_sum($writeTimes) / count($writeTimes), 2) : 0,
                'min' => count($writeTimes) > 0 ? min($writeTimes) : 0,
                'max' => count($writeTimes) > 0 ? max($writeTimes) : 0
            ]
        ];
    }

    /**
     * Get error rate metrics
     *
     * @return array
     */
    private function getErrorRateMetrics(): array
    {
        $recentMetrics = array_slice($this->metrics, -100);
        $totalOps = count($recentMetrics);
        $errors = count(array_filter($recentMetrics, fn($m) => !$m['success']));

        return [
            'total_operations' => $totalOps,
            'total_errors' => $errors,
            'error_rate_percent' => $totalOps > 0 ? round(($errors / $totalOps) * 100, 2) : 0
        ];
    }

    /**
     * Get throughput metrics
     *
     * @return array
     */
    private function getThroughputMetrics(): array
    {
        $recentMetrics = array_slice($this->metrics, -100);
        $timespan = 3600; // 1 hour

        if (count($recentMetrics) > 1) {
            $first = reset($recentMetrics);
            $last = end($recentMetrics);
            $actualTimespan = strtotime($last['timestamp']) - strtotime($first['timestamp']);
            if ($actualTimespan > 0) {
                $timespan = $actualTimespan;
            }
        }

        return [
            'operations_per_second' => round(count($recentMetrics) / $timespan, 2),
            'operations_per_hour' => count($recentMetrics) * (3600 / $timespan)
        ];
    }

    /**
     * Get cache efficiency metrics
     *
     * @return array
     */
    private function getCacheEfficiencyMetrics(): array
    {
        // This would typically come from Redis INFO or cache manager metrics
        return [
            'memory_efficiency' => 85.0, // Placeholder
            'eviction_rate' => 2.5, // Placeholder
            'fragmentation_ratio' => 1.2 // Placeholder
        ];
    }

    /**
     * Calculate overall status from health metrics and alerts
     *
     * @param array $health
     * @param array $alerts
     * @return string
     */
    private function calculateOverallStatus(array $health, array $alerts): string
    {
        // Check for critical alerts
        $criticalAlerts = array_filter($alerts, fn($alert) => $alert['severity'] === 'critical');
        if (!empty($criticalAlerts)) {
            return 'critical';
        }

        // Check Redis connectivity
        if (!($health['redis_connected'] ?? false)) {
            return 'critical';
        }

        // Check for high severity alerts
        $highSeverityAlerts = array_filter($alerts, fn($alert) => $alert['severity'] === 'high');
        if (!empty($highSeverityAlerts)) {
            return 'degraded';
        }

        // Check for any alerts
        if (!empty($alerts)) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Calculate Redis memory usage percentage
     *
     * @param array $info
     * @param array $config
     * @return float
     */
    private function calculateMemoryUsagePercent(array $info, array $config): float
    {
        $usedMemory = $info['used_memory'] ?? 0;
        $maxMemory = $config['maxmemory'] ?? 0;

        if ($maxMemory > 0) {
            return round(($usedMemory / $maxMemory) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Calculate Redis hit rate
     *
     * @param array $info
     * @return float
     */
    private function calculateRedisHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Calculate error rate from health metrics
     *
     * @param array $health
     * @return float
     */
    private function calculateErrorRate(array $health): float
    {
        $readErrors = $health['errors']['read_errors'] ?? 0;
        $writeErrors = $health['errors']['write_errors'] ?? 0;
        $totalOps = ($health['cache_hits'] ?? 0) + ($health['cache_misses'] ?? 0);

        if ($totalOps > 0) {
            return round((($readErrors + $writeErrors) / $totalOps) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Store health report for trend analysis
     *
     * @param array $report
     */
    private function storeHealthReport(array $report): void
    {
        try {
            DB::table('cache_health_reports')->insert([
                'timestamp' => Carbon::now(),
                'overall_status' => $report['overall_status'],
                'hit_ratio' => $report['cache_health']['hit_ratio'] ?? 0,
                'avg_read_time' => $report['cache_health']['avg_read_time'] ?? 0,
                'avg_write_time' => $report['cache_health']['avg_write_time'] ?? 0,
                'error_count' => count($report['alerts'] ?? []),
                'redis_memory_usage' => $report['redis_health']['memory']['memory_usage_percent'] ?? 0,
                'data' => json_encode($report),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Cleanup old reports (keep last 30 days)
            DB::table('cache_health_reports')
                ->where('created_at', '<', Carbon::now()->subDays(30))
                ->delete();

        } catch (\Exception $e) {
            Log::warning('Failed to store cache health report', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Persist metric to database
     *
     * @param array $metric
     */
    private function persistMetric(array $metric): void
    {
        try {
            DB::table('cache_metrics')->insert([
                'operation' => $metric['operation'],
                'duration_ms' => $metric['duration_ms'],
                'success' => $metric['success'],
                'metadata' => json_encode($metric['metadata']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

        } catch (\Exception $e) {
            // Don't log errors for metrics persistence to avoid log spam
            // Just silently fail to prevent impact on cache operations
        }
    }

    /**
     * Parse timeframe string to hours
     *
     * @param string $timeframe
     * @return int
     */
    private function parseTimeframe(string $timeframe): int
    {
        return match($timeframe) {
            '1h' => 1,
            '6h' => 6,
            '12h' => 12,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720,
            default => 24
        };
    }

    /**
     * Get total operations from trends
     *
     * @param array $trends
     * @return int
     */
    private function getTotalOperations(array $trends): int
    {
        $total = 0;
        foreach ($trends['trends'] ?? [] as $operationTrends) {
            foreach ($operationTrends as $trend) {
                $total += $trend['operation_count'] ?? 0;
            }
        }
        return $total;
    }

    /**
     * Get average response time from trends
     *
     * @param array $trends
     * @return float
     */
    private function getAverageResponseTime(array $trends): float
    {
        $totalDuration = 0;
        $totalOps = 0;

        foreach ($trends['trends'] ?? [] as $operationTrends) {
            foreach ($operationTrends as $trend) {
                $ops = $trend['operation_count'] ?? 0;
                $avgDuration = $trend['avg_duration'] ?? 0;
                $totalDuration += $ops * $avgDuration;
                $totalOps += $ops;
            }
        }

        return $totalOps > 0 ? round($totalDuration / $totalOps, 2) : 0.0;
    }

    /**
     * Get error rate from trends
     *
     * @param array $trends
     * @return float
     */
    private function getErrorRate(array $trends): float
    {
        $totalOps = 0;
        $totalErrors = 0;

        foreach ($trends['trends'] ?? [] as $operationTrends) {
            foreach ($operationTrends as $trend) {
                $totalOps += $trend['operation_count'] ?? 0;
                $totalErrors += $trend['error_count'] ?? 0;
            }
        }

        return $totalOps > 0 ? round(($totalErrors / $totalOps) * 100, 2) : 0.0;
    }

    /**
     * Generate performance recommendations
     *
     * @param array $health
     * @param array $trends
     * @return array
     */
    private function generateRecommendations(array $health, array $trends): array
    {
        $recommendations = [];

        // Hit ratio recommendations
        $hitRatio = $health['cache_health']['hit_ratio'] ?? 0;
        if ($hitRatio < 70) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => 'Cache hit ratio is low. Consider implementing cache warming strategies.',
                'action' => 'Implement cache warming for frequently accessed data'
            ];
        }

        // Response time recommendations
        $avgReadTime = $health['cache_health']['avg_read_time'] ?? 0;
        if ($avgReadTime > 50) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'Cache read times are high. Consider optimizing Redis configuration.',
                'action' => 'Review Redis memory settings and network latency'
            ];
        }

        // Memory usage recommendations
        $memoryUsage = $health['redis_health']['memory']['memory_usage_percent'] ?? 0;
        if ($memoryUsage > 80) {
            $recommendations[] = [
                'type' => 'capacity',
                'priority' => 'high',
                'message' => 'Redis memory usage is high. Consider increasing memory or implementing eviction policies.',
                'action' => 'Scale Redis memory or review cache TTL settings'
            ];
        }

        return $recommendations;
    }
}