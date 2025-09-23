<?php

namespace Tests\Unit\Queue;

use Tests\TestCase;
use App\Jobs\ProcessBrainDumpJob;
use App\Jobs\GenerateAiContentJob;
use App\Models\Content;
use App\Models\User;
use App\Services\QueueMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Carbon\Carbon;
use Mockery;

class QueueMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected QueueMonitoringService $monitoringService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->monitoringService = new QueueMonitoringService();

        Queue::fake();
        Event::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_tracks_queue_job_metrics()
    {
        // Given: A job being processed
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $job = new ProcessBrainDumpJob($content);

        // When: Job processing events occur
        $startTime = microtime(true);

        Event::dispatch(new JobProcessing('redis', $job));

        // Simulate processing time
        usleep(100000); // 100ms

        Event::dispatch(new JobProcessed('redis', $job));

        $endTime = microtime(true);
        $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Then: Metrics should be tracked
        $metrics = $this->monitoringService->getJobMetrics('ai-processing', 'ProcessBrainDumpJob');

        $this->assertArrayHasKey('total_processed', $metrics);
        $this->assertArrayHasKey('avg_processing_time', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
    }

    /** @test */
    public function it_monitors_queue_size_and_wait_times()
    {
        // Given: Multiple jobs in queue
        $contents = Content::factory()->count(5)->create(['user_id' => $this->user->id]);

        // When: Jobs are dispatched
        foreach ($contents as $content) {
            ProcessBrainDumpJob::dispatch($content)->onQueue('ai-processing');
        }

        // Then: Queue size should be monitored
        $queueSize = $this->monitoringService->getQueueSize('ai-processing');
        $this->assertEquals(5, $queueSize);

        // And: Wait times should be tracked
        $avgWaitTime = $this->monitoringService->getAverageWaitTime('ai-processing');
        $this->assertIsNumeric($avgWaitTime);
    }

    /** @test */
    public function it_tracks_job_failure_rates()
    {
        // Given: Jobs that will fail
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $job = new ProcessBrainDumpJob($content);

        // When: Job fails
        $exception = new \Exception('Job failed');
        Event::dispatch(new JobFailed('redis', $job, $exception));

        // Then: Failure rate should be tracked
        $failureRate = $this->monitoringService->getFailureRate('ai-processing');
        $this->assertGreaterThan(0, $failureRate);

        $failureMetrics = $this->monitoringService->getFailureMetrics('ai-processing');
        $this->assertArrayHasKey('total_failures', $failureMetrics);
        $this->assertArrayHasKey('common_errors', $failureMetrics);
    }

    /** @test */
    public function it_monitors_queue_worker_health()
    {
        // Given: Queue workers running
        $workers = [
            ['id' => 'worker-1', 'queue' => 'ai-processing', 'status' => 'active'],
            ['id' => 'worker-2', 'queue' => 'ai-processing', 'status' => 'idle'],
            ['id' => 'worker-3', 'queue' => 'ai-content-generation', 'status' => 'active'],
        ];

        // When: Checking worker health
        foreach ($workers as $worker) {
            Cache::put("worker_heartbeat:{$worker['id']}", [
                'queue' => $worker['queue'],
                'status' => $worker['status'],
                'last_seen' => now(),
                'memory_usage' => rand(100, 500) . 'MB',
                'jobs_processed' => rand(10, 100)
            ], 300);
        }

        // Then: Worker health should be monitored
        $workerHealth = $this->monitoringService->getWorkerHealth();

        $this->assertArrayHasKey('total_workers', $workerHealth);
        $this->assertArrayHasKey('active_workers', $workerHealth);
        $this->assertArrayHasKey('idle_workers', $workerHealth);
        $this->assertEquals(3, $workerHealth['total_workers']);
    }

    /** @test */
    public function it_tracks_memory_usage_per_queue()
    {
        // Given: Memory usage data
        $memoryData = [
            'ai-processing' => ['current' => 256, 'peak' => 512, 'average' => 300],
            'ai-content-generation' => ['current' => 128, 'peak' => 256, 'average' => 180],
            'ai-embeddings' => ['current' => 384, 'peak' => 768, 'average' => 450],
        ];

        // When: Tracking memory usage
        foreach ($memoryData as $queue => $usage) {
            Cache::put("queue_memory:{$queue}", $usage, 300);
        }

        // Then: Memory usage should be monitored
        $memoryMetrics = $this->monitoringService->getMemoryUsage();

        $this->assertArrayHasKey('by_queue', $memoryMetrics);
        $this->assertArrayHasKey('total_usage', $memoryMetrics);
        $this->assertArrayHasKey('peak_usage', $memoryMetrics);
    }

    /** @test */
    public function it_implements_alerting_for_queue_problems()
    {
        // Given: Alert thresholds
        $thresholds = [
            'queue_size_warning' => 50,
            'queue_size_critical' => 100,
            'failure_rate_warning' => 0.1, // 10%
            'failure_rate_critical' => 0.2, // 20%
            'avg_wait_time_warning' => 300, // 5 minutes
            'avg_wait_time_critical' => 600, // 10 minutes
        ];

        // When: Queue size exceeds threshold
        Cache::put('queue_size:ai-processing', 75, 300);

        $alerts = $this->monitoringService->checkAlerts();

        // Then: Alerts should be generated
        $this->assertNotEmpty($alerts);
        $this->assertArrayHasKey('queue_size_warnings', $alerts);
    }

    /** @test */
    public function it_monitors_job_throughput_rates()
    {
        // Given: Job processing data over time
        $timeIntervals = [
            now()->subMinutes(5) => 10,
            now()->subMinutes(4) => 12,
            now()->subMinutes(3) => 8,
            now()->subMinutes(2) => 15,
            now()->subMinutes(1) => 11,
        ];

        // When: Recording throughput data
        foreach ($timeIntervals as $time => $jobCount) {
            Cache::put("throughput:ai-processing:{$time->timestamp}", $jobCount, 3600);
        }

        // Then: Throughput should be calculated
        $throughput = $this->monitoringService->getThroughputRate('ai-processing', 5);

        $this->assertArrayHasKey('jobs_per_minute', $throughput);
        $this->assertArrayHasKey('trend', $throughput);
        $this->assertGreaterThan(0, $throughput['jobs_per_minute']);
    }

    /** @test */
    public function it_tracks_job_retry_patterns()
    {
        // Given: Jobs with retry attempts
        $retryData = [
            'ProcessBrainDumpJob' => ['attempts' => [1, 2, 1, 3, 1, 2], 'success_after_retry' => 4],
            'GenerateAiContentJob' => ['attempts' => [1, 1, 2, 1, 3], 'success_after_retry' => 3],
        ];

        // When: Tracking retry patterns
        foreach ($retryData as $jobClass => $data) {
            Cache::put("job_retries:{$jobClass}", $data, 3600);
        }

        // Then: Retry patterns should be analyzed
        $retryMetrics = $this->monitoringService->getRetryMetrics();

        $this->assertArrayHasKey('by_job_type', $retryMetrics);
        $this->assertArrayHasKey('avg_retries_before_success', $retryMetrics);
        $this->assertArrayHasKey('retry_success_rate', $retryMetrics);
    }

    /** @test */
    public function it_monitors_queue_deadlock_detection()
    {
        // Given: Jobs that might cause deadlocks
        $deadlockRisk = [
            'ai-processing' => [
                'stuck_jobs' => 2,
                'long_running_jobs' => 1,
                'avg_processing_time' => 300,
                'max_processing_time' => 1800 // 30 minutes
            ]
        ];

        // When: Checking for deadlock risks
        foreach ($deadlockRisk as $queue => $risk) {
            Cache::put("deadlock_risk:{$queue}", $risk, 300);
        }

        // Then: Deadlock detection should work
        $deadlockAlerts = $this->monitoringService->checkDeadlockRisk();

        $this->assertIsArray($deadlockAlerts);
        if (!empty($deadlockAlerts)) {
            $this->assertArrayHasKey('queue', $deadlockAlerts[0]);
            $this->assertArrayHasKey('risk_level', $deadlockAlerts[0]);
        }
    }

    /** @test */
    public function it_tracks_cost_metrics_for_ai_operations()
    {
        // Given: AI operation costs
        $costData = [
            'today' => ['total_cost' => 25.50, 'job_count' => 100, 'avg_cost_per_job' => 0.255],
            'yesterday' => ['total_cost' => 22.30, 'job_count' => 95, 'avg_cost_per_job' => 0.235],
            'this_week' => ['total_cost' => 150.75, 'job_count' => 650, 'avg_cost_per_job' => 0.232],
        ];

        // When: Tracking costs
        foreach ($costData as $period => $data) {
            Cache::put("ai_costs:{$period}", $data, 3600);
        }

        // Then: Cost metrics should be available
        $costMetrics = $this->monitoringService->getCostMetrics();

        $this->assertArrayHasKey('daily_cost', $costMetrics);
        $this->assertArrayHasKey('weekly_cost', $costMetrics);
        $this->assertArrayHasKey('cost_trend', $costMetrics);
    }

    /** @test */
    public function it_monitors_queue_processing_patterns()
    {
        // Given: Processing patterns over time
        $patterns = [
            'peak_hours' => [9, 10, 11, 14, 15, 16], // Hours with highest load
            'low_hours' => [1, 2, 3, 4, 5, 22, 23],  // Hours with lowest load
            'daily_patterns' => [
                'monday' => 120,
                'tuesday' => 110,
                'wednesday' => 130,
                'thursday' => 125,
                'friday' => 100,
                'saturday' => 40,
                'sunday' => 35
            ]
        ];

        // When: Analyzing patterns
        Cache::put('queue_patterns:ai-processing', $patterns, 3600);

        // Then: Patterns should be identified
        $patternAnalysis = $this->monitoringService->getProcessingPatterns('ai-processing');

        $this->assertArrayHasKey('peak_hours', $patternAnalysis);
        $this->assertArrayHasKey('recommended_scaling', $patternAnalysis);
    }

    /** @test */
    public function it_provides_real_time_dashboard_metrics()
    {
        // Given: Real-time data
        $realTimeData = [
            'active_jobs' => 15,
            'pending_jobs' => 45,
            'failed_jobs_last_hour' => 2,
            'avg_processing_time_last_hour' => 125,
            'worker_cpu_usage' => 68.5,
            'worker_memory_usage' => 72.3,
        ];

        // When: Getting dashboard metrics
        foreach ($realTimeData as $metric => $value) {
            Cache::put("realtime:{$metric}", $value, 60);
        }

        // Then: Dashboard should show current status
        $dashboardMetrics = $this->monitoringService->getDashboardMetrics();

        $this->assertArrayHasKey('current_load', $dashboardMetrics);
        $this->assertArrayHasKey('system_health', $dashboardMetrics);
        $this->assertArrayHasKey('recent_performance', $dashboardMetrics);
    }

    /** @test */
    public function it_implements_performance_benchmarking()
    {
        // Given: Baseline performance metrics
        $baseline = [
            'ProcessBrainDumpJob' => ['avg_time' => 45, 'success_rate' => 0.95],
            'GenerateAiContentJob' => ['avg_time' => 32, 'success_rate' => 0.92],
            'GenerateVectorEmbeddingsJob' => ['avg_time' => 28, 'success_rate' => 0.98],
        ];

        // When: Comparing current performance
        $current = [
            'ProcessBrainDumpJob' => ['avg_time' => 52, 'success_rate' => 0.89],
            'GenerateAiContentJob' => ['avg_time' => 35, 'success_rate' => 0.91],
            'GenerateVectorEmbeddingsJob' => ['avg_time' => 25, 'success_rate' => 0.99],
        ];

        // Then: Performance comparison should be available
        $benchmark = $this->monitoringService->benchmarkPerformance($baseline, $current);

        $this->assertArrayHasKey('performance_changes', $benchmark);
        $this->assertArrayHasKey('degraded_jobs', $benchmark);
        $this->assertArrayHasKey('improved_jobs', $benchmark);
    }

    /** @test */
    public function it_logs_monitoring_events_appropriately()
    {
        // Given: Monitoring events
        Log::shouldReceive('info')
            ->once()
            ->with('Queue monitoring metrics updated', Mockery::type('array'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Queue size threshold exceeded', Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('Queue worker health check failed', Mockery::type('array'));

        // When: Monitoring events occur
        $this->monitoringService->updateMetrics();
        $this->monitoringService->checkThresholds();
        $this->monitoringService->checkWorkerHealth();
    }

    /** @test */
    public function it_supports_custom_metric_collection()
    {
        // Given: Custom metrics
        $customMetrics = [
            'ai_api_response_time' => 250,
            'embedding_generation_accuracy' => 0.87,
            'feedback_processing_effectiveness' => 0.92,
        ];

        // When: Recording custom metrics
        foreach ($customMetrics as $metric => $value) {
            $this->monitoringService->recordCustomMetric($metric, $value);
        }

        // Then: Custom metrics should be available
        $allMetrics = $this->monitoringService->getAllMetrics();

        $this->assertArrayHasKey('custom_metrics', $allMetrics);
        $this->assertEquals(250, $allMetrics['custom_metrics']['ai_api_response_time']);
    }

    /** @test */
    public function it_provides_historical_trend_analysis()
    {
        // Given: Historical data points
        $historicalData = [];
        for ($i = 30; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $historicalData[$date->format('Y-m-d')] = [
                'jobs_processed' => rand(80, 120),
                'avg_processing_time' => rand(40, 60),
                'failure_rate' => rand(5, 15) / 100,
            ];
        }

        // When: Analyzing trends
        foreach ($historicalData as $date => $data) {
            Cache::put("historical:ai-processing:{$date}", $data, 86400);
        }

        // Then: Trend analysis should be available
        $trends = $this->monitoringService->getTrendAnalysis('ai-processing', 30);

        $this->assertArrayHasKey('processing_time_trend', $trends);
        $this->assertArrayHasKey('throughput_trend', $trends);
        $this->assertArrayHasKey('reliability_trend', $trends);
    }
}