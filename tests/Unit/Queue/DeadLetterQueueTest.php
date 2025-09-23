<?php

namespace Tests\Unit\Queue;

use Tests\TestCase;
use App\Jobs\ProcessBrainDumpJob;
use App\Jobs\GenerateAiContentJob;
use App\Models\Content;
use App\Models\User;
use App\Services\DeadLetterQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DeadLetterQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DeadLetterQueueService $dlqService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->dlqService = new DeadLetterQueueService();

        Queue::fake();
    }

    /** @test */
    public function it_moves_failed_jobs_to_dead_letter_queue()
    {
        // Given: A job that will fail after max retries
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $job = new ProcessBrainDumpJob($content);

        // When: Job fails after max attempts
        $exception = new \Exception('Max retries exceeded');
        $job->failed($exception);

        // Then: Job should be recorded in failed jobs table
        $this->assertDatabaseHas('failed_jobs', [
            'queue' => 'ai-processing',
            'exception' => 'Max retries exceeded'
        ]);
    }

    /** @test */
    public function it_categorizes_failed_jobs_by_failure_type()
    {
        // Given: Different types of failures
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        $failureTypes = [
            'timeout' => 'Request timeout after 30 seconds',
            'rate_limit' => 'Rate limit exceeded',
            'auth_error' => 'Authentication failed',
            'service_unavailable' => 'Service temporarily unavailable',
            'validation_error' => 'Invalid input data',
        ];

        // When: Jobs fail with different error types
        foreach ($failureTypes as $type => $message) {
            $job = new ProcessBrainDumpJob($content);
            $exception = new \Exception($message);
            $job->failed($exception);

            // Then: Failure should be categorized
            $category = $this->dlqService->categorizeFailure($exception);
            $this->assertContains($category, ['timeout', 'rate_limit', 'auth_error', 'service_error', 'validation_error']);
        }
    }

    /** @test */
    public function it_implements_automatic_retry_for_transient_failures()
    {
        // Given: Transient failure types that should be retried
        $transientFailures = [
            'Request timeout',
            'Service temporarily unavailable',
            'Network connectivity error',
            'Rate limit exceeded'
        ];

        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Checking if failures should be retried
        foreach ($transientFailures as $failureMessage) {
            $exception = new \Exception($failureMessage);
            $shouldRetry = $this->dlqService->shouldAutoRetry($exception);

            // Then: Transient failures should be marked for retry
            $this->assertTrue($shouldRetry, "Should retry transient failure: {$failureMessage}");
        }
    }

    /** @test */
    public function it_prevents_infinite_retry_loops()
    {
        // Given: Job that keeps failing
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $job = new ProcessBrainDumpJob($content);

        // When: Job has been retried many times
        $retryCount = 10;
        $maxAutoRetries = 5;

        // Then: Auto-retry should be disabled after threshold
        $shouldRetry = $this->dlqService->shouldAutoRetry(
            new \Exception('Persistent failure'),
            $retryCount,
            $maxAutoRetries
        );

        $this->assertFalse($shouldRetry, 'Should not retry after max auto-retry attempts');
    }

    /** @test */
    public function it_provides_failed_job_analysis_and_reporting()
    {
        // Given: Multiple failed jobs
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        $failures = [
            ['type' => 'timeout', 'count' => 5],
            ['type' => 'rate_limit', 'count' => 3],
            ['type' => 'auth_error', 'count' => 2],
            ['type' => 'service_error', 'count' => 1],
        ];

        // When: Recording failures
        foreach ($failures as $failure) {
            for ($i = 0; $i < $failure['count']; $i++) {
                DB::table('failed_jobs')->insert([
                    'uuid' => \Str::uuid(),
                    'connection' => 'redis',
                    'queue' => 'ai-processing',
                    'payload' => json_encode(['job' => 'ProcessBrainDumpJob']),
                    'exception' => "Error type: {$failure['type']}",
                    'failed_at' => now()
                ]);
            }
        }

        // Then: Analysis should provide insights
        $analysis = $this->dlqService->getFailureAnalysis();

        $this->assertArrayHasKey('total_failures', $analysis);
        $this->assertArrayHasKey('failure_by_type', $analysis);
        $this->assertArrayHasKey('most_common_errors', $analysis);
        $this->assertEquals(11, $analysis['total_failures']);
    }

    /** @test */
    public function it_implements_dead_letter_queue_cleanup()
    {
        // Given: Old failed jobs
        $oldDate = now()->subDays(31);
        $recentDate = now()->subDays(5);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'ai-processing',
                'payload' => json_encode(['job' => 'ProcessBrainDumpJob']),
                'exception' => 'Old failure',
                'failed_at' => $oldDate
            ],
            [
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'ai-processing',
                'payload' => json_encode(['job' => 'GenerateAiContentJob']),
                'exception' => 'Recent failure',
                'failed_at' => $recentDate
            ]
        ]);

        // When: Running cleanup
        $cleanedCount = $this->dlqService->cleanupOldFailures(30); // 30 days retention

        // Then: Old failures should be removed
        $this->assertEquals(1, $cleanedCount);
        $this->assertDatabaseMissing('failed_jobs', [
            'exception' => 'Old failure'
        ]);
        $this->assertDatabaseHas('failed_jobs', [
            'exception' => 'Recent failure'
        ]);
    }

    /** @test */
    public function it_supports_manual_job_requeuing()
    {
        // Given: Failed job in dead letter queue
        $failedJobId = DB::table('failed_jobs')->insertGetId([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'ai-processing',
            'payload' => json_encode([
                'job' => 'App\\Jobs\\ProcessBrainDumpJob',
                'data' => ['content_id' => 123]
            ]),
            'exception' => 'Manual requeue test',
            'failed_at' => now()
        ]);

        // When: Manually requeuing the job
        $requeued = $this->dlqService->requeueFailedJob($failedJobId);

        // Then: Job should be requeued successfully
        $this->assertTrue($requeued);

        // And: Job should be removed from failed jobs table
        $this->assertDatabaseMissing('failed_jobs', [
            'id' => $failedJobId
        ]);
    }

    /** @test */
    public function it_implements_bulk_job_requeuing()
    {
        // Given: Multiple failed jobs of same type
        $failedJobIds = [];
        for ($i = 0; $i < 5; $i++) {
            $failedJobIds[] = DB::table('failed_jobs')->insertGetId([
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'ai-processing',
                'payload' => json_encode([
                    'job' => 'App\\Jobs\\ProcessBrainDumpJob',
                    'data' => ['content_id' => 100 + $i]
                ]),
                'exception' => 'Bulk requeue test',
                'failed_at' => now()
            ]);
        }

        // When: Bulk requeuing jobs
        $requeuedCount = $this->dlqService->requeueFailedJobsBulk($failedJobIds);

        // Then: All jobs should be requeued
        $this->assertEquals(5, $requeuedCount);

        // And: Jobs should be removed from failed jobs table
        foreach ($failedJobIds as $id) {
            $this->assertDatabaseMissing('failed_jobs', ['id' => $id]);
        }
    }

    /** @test */
    public function it_tracks_dead_letter_queue_metrics()
    {
        // Given: Failed jobs over time
        $dates = [
            now()->subDays(1),
            now()->subDays(2),
            now()->subDays(3),
        ];

        foreach ($dates as $date) {
            DB::table('failed_jobs')->insert([
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'ai-processing',
                'payload' => json_encode(['job' => 'ProcessBrainDumpJob']),
                'exception' => 'Test failure',
                'failed_at' => $date
            ]);
        }

        // When: Getting DLQ metrics
        $metrics = $this->dlqService->getMetrics();

        // Then: Metrics should be available
        $this->assertArrayHasKey('total_failed_jobs', $metrics);
        $this->assertArrayHasKey('failed_jobs_by_day', $metrics);
        $this->assertArrayHasKey('failed_jobs_by_queue', $metrics);
        $this->assertArrayHasKey('failed_jobs_by_type', $metrics);
        $this->assertEquals(3, $metrics['total_failed_jobs']);
    }

    /** @test */
    public function it_implements_dead_letter_queue_alerting()
    {
        // Given: Alert thresholds
        $alertThresholds = [
            'critical_count' => 10,
            'warning_count' => 5,
            'failure_rate_threshold' => 0.2, // 20%
        ];

        // When: Failed job count exceeds threshold
        for ($i = 0; $i < 12; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => 'ai-processing',
                'payload' => json_encode(['job' => 'ProcessBrainDumpJob']),
                'exception' => 'Alert test failure',
                'failed_at' => now()
            ]);
        }

        // Then: Alerts should be triggered
        $alerts = $this->dlqService->checkAlerts($alertThresholds);

        $this->assertNotEmpty($alerts);
        $this->assertArrayHasKey('level', $alerts[0]);
        $this->assertEquals('critical', $alerts[0]['level']);
    }

    /** @test */
    public function it_supports_failure_pattern_detection()
    {
        // Given: Failures with patterns
        $patterns = [
            'timeout_pattern' => 'Request timeout after 30 seconds',
            'rate_limit_pattern' => 'Rate limit exceeded for provider',
            'auth_pattern' => 'Authentication failed - invalid API key',
        ];

        foreach ($patterns as $pattern => $message) {
            for ($i = 0; $i < 3; $i++) {
                DB::table('failed_jobs')->insert([
                    'uuid' => \Str::uuid(),
                    'connection' => 'redis',
                    'queue' => 'ai-processing',
                    'payload' => json_encode(['job' => 'ProcessBrainDumpJob']),
                    'exception' => $message,
                    'failed_at' => now()->subMinutes($i * 10)
                ]);
            }
        }

        // When: Detecting patterns
        $patterns = $this->dlqService->detectFailurePatterns();

        // Then: Patterns should be identified
        $this->assertNotEmpty($patterns);
        $this->assertArrayHasKey('recurring_errors', $patterns);
        $this->assertArrayHasKey('error_frequency', $patterns);
    }

    /** @test */
    public function it_implements_dead_letter_queue_recovery_strategies()
    {
        // Given: Different types of failed jobs
        $recoveryStrategies = [
            'timeout' => 'retry_with_longer_timeout',
            'rate_limit' => 'retry_with_backoff',
            'auth_error' => 'manual_intervention_required',
            'validation_error' => 'skip_and_log',
        ];

        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Determining recovery strategies
        foreach ($recoveryStrategies as $errorType => $expectedStrategy) {
            $exception = new \Exception("Error type: {$errorType}");
            $strategy = $this->dlqService->determineRecoveryStrategy($exception);

            // Then: Appropriate strategy should be recommended
            $this->assertNotNull($strategy);
            $this->assertArrayHasKey('action', $strategy);
        }
    }

    /** @test */
    public function it_logs_dead_letter_queue_activities()
    {
        // Given: DLQ operations
        Log::shouldReceive('warning')
            ->once()
            ->with('Job moved to dead letter queue', \Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Failed job requeued from DLQ', \Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('DLQ alert triggered', \Mockery::type('array'));

        // When: DLQ operations occur
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $job = new ProcessBrainDumpJob($content);

        $job->failed(new \Exception('Test failure'));

        // Simulate requeue
        $failedJobId = DB::table('failed_jobs')->insertGetId([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'ai-processing',
            'payload' => json_encode(['job' => 'ProcessBrainDumpJob']),
            'exception' => 'Requeue test',
            'failed_at' => now()
        ]);

        $this->dlqService->requeueFailedJob($failedJobId);

        // Simulate alert
        $this->dlqService->triggerAlert('critical', 'Too many failed jobs');
    }

    /** @test */
    public function it_supports_dead_letter_queue_archiving()
    {
        // Given: Very old failed jobs
        $archiveDate = now()->subDays(91); // 3 months old

        $oldJobId = DB::table('failed_jobs')->insertGetId([
            'uuid' => \Str::uuid(),
            'connection' => 'redis',
            'queue' => 'ai-processing',
            'payload' => json_encode(['job' => 'ProcessBrainDumpJob']),
            'exception' => 'Very old failure',
            'failed_at' => $archiveDate
        ]);

        // When: Archiving old failures
        $archivedCount = $this->dlqService->archiveOldFailures(90); // 90 days

        // Then: Old jobs should be archived
        $this->assertEquals(1, $archivedCount);

        // And: Job should be moved to archive table
        $this->assertDatabaseHas('failed_jobs_archive', [
            'original_id' => $oldJobId,
            'exception' => 'Very old failure'
        ]);

        // And: Job should be removed from main table
        $this->assertDatabaseMissing('failed_jobs', [
            'id' => $oldJobId
        ]);
    }

    /** @test */
    public function it_provides_dead_letter_queue_dashboard_data()
    {
        // Given: Various failed jobs
        for ($i = 0; $i < 15; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => \Str::uuid(),
                'connection' => 'redis',
                'queue' => ['ai-processing', 'ai-content-generation', 'ai-embeddings'][rand(0, 2)],
                'payload' => json_encode(['job' => 'TestJob']),
                'exception' => ['Timeout', 'Rate limit', 'Auth error'][rand(0, 2)],
                'failed_at' => now()->subHours(rand(1, 24))
            ]);
        }

        // When: Getting dashboard data
        $dashboardData = $this->dlqService->getDashboardData();

        // Then: Dashboard should have comprehensive data
        $this->assertArrayHasKey('summary', $dashboardData);
        $this->assertArrayHasKey('charts', $dashboardData);
        $this->assertArrayHasKey('recent_failures', $dashboardData);
        $this->assertArrayHasKey('trending_errors', $dashboardData);

        $summary = $dashboardData['summary'];
        $this->assertArrayHasKey('total_failed', $summary);
        $this->assertArrayHasKey('failed_today', $summary);
        $this->assertArrayHasKey('most_common_error', $summary);
    }
}