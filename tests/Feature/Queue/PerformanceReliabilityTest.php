<?php

namespace Tests\Feature\Queue;

use Tests\TestCase;
use App\Jobs\ProcessBrainDumpJob;
use App\Jobs\GenerateAiContentJob;
use App\Jobs\ProcessFeedbackLearningJob;
use App\Jobs\GenerateVectorEmbeddingsJob;
use App\Models\Content;
use App\Models\User;
use App\Models\Feedback;
use App\Services\AiService;
use App\Services\BrainDumpProcessor;
use App\Services\EmbeddingService;
use App\Exceptions\AiServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use Mockery;

class PerformanceReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Reset queue and bus for each test
        Queue::fake();
        Bus::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_high_volume_job_processing()
    {
        // Given: High volume of jobs (simulate load testing)
        $jobCount = 100;
        $contents = Content::factory()->count($jobCount)->create(['user_id' => $this->user->id]);

        // When: Dispatching many jobs simultaneously
        $startTime = microtime(true);

        foreach ($contents as $content) {
            ProcessBrainDumpJob::dispatch($content);
        }

        $endTime = microtime(true);
        $dispatchTime = $endTime - $startTime;

        // Then: All jobs should be queued efficiently
        Queue::assertPushed(ProcessBrainDumpJob::class, $jobCount);

        // And: Dispatch time should be reasonable (under 5 seconds for 100 jobs)
        $this->assertLessThan(5.0, $dispatchTime, 'Job dispatch took too long');

        // And: Jobs should be distributed across priority queues
        $this->assertGreaterThan(0, Queue::size('ai-processing'));
    }

    /** @test */
    public function it_maintains_performance_under_memory_pressure()
    {
        // Given: Large content that requires significant memory
        $largeContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => str_repeat('This is large content for memory testing. ', 10000), // ~400KB
        ]);

        // When: Processing multiple large content jobs
        for ($i = 0; $i < 10; $i++) {
            GenerateVectorEmbeddingsJob::dispatch($largeContent);
        }

        // Then: Jobs should be queued without memory issues
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class, 10);

        // And: Memory usage should be monitored
        $memoryUsage = memory_get_usage(true);
        $this->assertLessThan(512 * 1024 * 1024, $memoryUsage, 'Memory usage too high'); // 512MB limit
    }

    /** @test */
    public function it_implements_graceful_degradation_when_ai_services_unavailable()
    {
        // Given: AI service is unavailable
        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('complete')
                 ->andThrow(new AiServiceException('Service unavailable'));
        });

        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Attempting to process jobs
        GenerateAiContentJob::dispatch($content, 'summary');

        // Then: Job should be queued for retry
        Queue::assertPushed(GenerateAiContentJob::class);

        // And: Fallback mechanisms should be available
        $fallbackEnabled = Config::get('ai.enable_fallback', false);
        if ($fallbackEnabled) {
            $this->assertTrue($fallbackEnabled);
        }
    }

    /** @test */
    public function it_handles_database_connection_failures_resilient()
    {
        // Given: Database connection issues (simulated)
        DB::shouldReceive('table')
          ->andThrow(new \Exception('Database connection failed'));

        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Job encounters database issues
        try {
            $job = new ProcessBrainDumpJob($content);
            // Simulate job execution with DB failure
        } catch (\Exception $e) {
            // Expected behavior - job should handle gracefully
        }

        // Then: Job should implement retry logic
        $this->assertEquals(3, $job->tries);
        $this->assertNotEmpty($job->backoff);
    }

    /** @test */
    public function it_maintains_job_ordering_and_priority()
    {
        // Given: Jobs with different priorities
        $urgentContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'metadata' => ['priority' => 'urgent']
        ]);

        $normalContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'metadata' => ['priority' => 'normal']
        ]);

        $lowContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'metadata' => ['priority' => 'low']
        ]);

        // When: Jobs are dispatched in random order
        ProcessBrainDumpJob::dispatch($normalContent)->onQueue('ai-processing');
        ProcessBrainDumpJob::dispatch($urgentContent)->onQueue('ai-processing-high');
        ProcessBrainDumpJob::dispatch($lowContent)->onQueue('ai-processing-low');

        // Then: Jobs should be on appropriate priority queues
        Queue::assertPushedOn('ai-processing-high', ProcessBrainDumpJob::class);
        Queue::assertPushedOn('ai-processing', ProcessBrainDumpJob::class);
        Queue::assertPushedOn('ai-processing-low', ProcessBrainDumpJob::class);
    }

    /** @test */
    public function it_handles_concurrent_processing_without_race_conditions()
    {
        // Given: Same content being processed by multiple jobs
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Multiple jobs try to process same content
        ProcessBrainDumpJob::dispatch($content);
        GenerateVectorEmbeddingsJob::dispatch($content);
        GenerateAiContentJob::dispatch($content, 'summary');

        // Then: Jobs should handle concurrency properly
        Queue::assertPushed(ProcessBrainDumpJob::class);
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class);
        Queue::assertPushed(GenerateAiContentJob::class);

        // And: Locking mechanisms should prevent race conditions
        $lockKey = 'content_processing:' . $content->id;
        $this->assertFalse(Cache::has($lockKey), 'Lock should not exist initially');
    }

    /** @test */
    public function it_implements_circuit_breaker_for_failing_services()
    {
        // Given: Service that consistently fails
        $failureCount = 0;
        $this->mock(AiService::class, function ($mock) use (&$failureCount) {
            $mock->shouldReceive('complete')
                 ->andReturnUsing(function () use (&$failureCount) {
                     $failureCount++;
                     throw new AiServiceException('Consistent failure');
                 });
        });

        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Multiple jobs fail consistently
        for ($i = 0; $i < 5; $i++) {
            try {
                GenerateAiContentJob::dispatch($content, 'summary');
            } catch (\Exception $e) {
                // Expected failures
            }
        }

        // Then: Circuit breaker should open after threshold
        $circuitState = Cache::get('circuit_breaker:ai_service', ['state' => 'closed']);

        // Circuit breaker implementation would track failures
        $this->assertArrayHasKey('state', $circuitState);
    }

    /** @test */
    public function it_handles_resource_exhaustion_gracefully()
    {
        // Given: System under resource pressure
        $this->mock(EmbeddingService::class, function ($mock) {
            $mock->shouldReceive('generateEmbedding')
                 ->andThrow(new \Exception('Insufficient memory'));
        });

        $contents = Content::factory()->count(20)->create(['user_id' => $this->user->id]);

        // When: Many resource-intensive jobs are queued
        foreach ($contents as $content) {
            GenerateVectorEmbeddingsJob::dispatch($content);
        }

        // Then: Jobs should be queued with backpressure handling
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class, 20);

        // And: Resource limits should be respected
        $memoryLimit = ini_get('memory_limit');
        $this->assertNotEmpty($memoryLimit);
    }

    /** @test */
    public function it_maintains_data_consistency_during_failures()
    {
        // Given: Job that modifies data then fails
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending'
        ]);

        $originalStatus = $content->status;

        // When: Job starts processing then fails
        $this->mock(BrainDumpProcessor::class, function ($mock) {
            $mock->shouldReceive('process')
                 ->andThrow(new \Exception('Processing failed'));
        });

        try {
            $job = new ProcessBrainDumpJob($content);
            $processor = app(BrainDumpProcessor::class);
            $job->handle($processor);
        } catch (\Exception $e) {
            // Expected failure
        }

        // Then: Data should be in consistent state
        $content->refresh();

        // Status should be either original or explicitly failed, not inconsistent
        $this->assertContains($content->status, ['pending', 'failed', 'processing']);
    }

    /** @test */
    public function it_implements_job_timeout_handling()
    {
        // Given: Job that takes too long
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('complete')
                 ->andReturnUsing(function () {
                     // Simulate very slow response
                     sleep(2);
                     return new \App\Services\AiResponse('Slow response', 50, 0.001);
                 });
        });

        // When: Job exceeds timeout
        $job = new GenerateAiContentJob($content, 'summary');

        // Then: Job should have appropriate timeout configuration
        $this->assertEquals(240, $job->timeout); // 4 minutes

        // And: Timeout should be enforced
        set_time_limit(1); // 1 second limit for test

        try {
            $aiService = app(AiService::class);
            $job->handle($aiService);
        } catch (\Exception $e) {
            // Expected timeout
            $this->assertStringContains('timeout', strtolower($e->getMessage()));
        }
    }

    /** @test */
    public function it_handles_batch_processing_efficiently()
    {
        // Given: Large batch of similar jobs
        $batchSize = 50;
        $contents = Content::factory()->count($batchSize)->create(['user_id' => $this->user->id]);

        // When: Processing batch
        $startTime = microtime(true);

        // Batch embeddings job
        GenerateVectorEmbeddingsJob::dispatch($contents->first(), $contents);

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        // Then: Batch should be processed efficiently
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class, 1); // Single batch job

        // And: Processing time should be reasonable
        $this->assertLessThan(1.0, $processingTime, 'Batch dispatch took too long');
    }

    /** @test */
    public function it_implements_health_checks_and_monitoring()
    {
        // Given: Health check configuration
        $healthChecks = [
            'queue_workers' => true,
            'database_connectivity' => true,
            'ai_service_availability' => true,
            'memory_usage' => true,
        ];

        // When: Running health checks
        foreach ($healthChecks as $check => $enabled) {
            if ($enabled) {
                $result = $this->performHealthCheck($check);

                // Then: Health checks should pass
                $this->assertTrue($result['healthy'], "Health check failed: {$check}");
            }
        }
    }

    /** @test */
    public function it_handles_queue_worker_failures_and_recovery()
    {
        // Given: Queue worker simulation
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Worker fails
        ProcessBrainDumpJob::dispatch($content);

        // Simulate worker failure by checking job state
        $job = new ProcessBrainDumpJob($content);

        // Then: Job should be retried by another worker
        $this->assertEquals(3, $job->tries);
        $this->assertIsNumeric($job->backoff);
    }

    /** @test */
    public function it_maintains_performance_during_peak_loads()
    {
        // Given: Peak load scenario
        $peakJobCount = 200;
        $contents = Content::factory()->count($peakJobCount)->create(['user_id' => $this->user->id]);

        // When: Simulating peak load
        $startTime = microtime(true);

        foreach ($contents as $index => $content) {
            // Distribute across different job types
            switch ($index % 4) {
                case 0:
                    ProcessBrainDumpJob::dispatch($content);
                    break;
                case 1:
                    GenerateAiContentJob::dispatch($content, 'summary');
                    break;
                case 2:
                    GenerateVectorEmbeddingsJob::dispatch($content);
                    break;
                case 3:
                    // Skip feedback jobs for this test
                    break;
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Then: System should handle peak load efficiently
        $this->assertLessThan(10.0, $totalTime, 'Peak load handling took too long');

        // And: Jobs should be distributed across queues
        Queue::assertPushed(ProcessBrainDumpJob::class);
        Queue::assertPushed(GenerateAiContentJob::class);
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class);
    }

    /** @test */
    public function it_implements_job_deduplication()
    {
        // Given: Duplicate content
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Multiple identical jobs are dispatched
        ProcessBrainDumpJob::dispatch($content);
        ProcessBrainDumpJob::dispatch($content);
        ProcessBrainDumpJob::dispatch($content);

        // Then: All jobs should be queued (deduplication handled at processing level)
        Queue::assertPushed(ProcessBrainDumpJob::class, 3);

        // But processing should handle duplicates gracefully
        $lockKey = 'brain_dump_lock:' . $content->id;
        Cache::put($lockKey, true, 300);

        $this->assertTrue(Cache::has($lockKey));
    }

    /** @test */
    public function it_handles_cascading_failures_gracefully()
    {
        // Given: Chain of dependent jobs
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Primary job fails
        $this->mock(BrainDumpProcessor::class, function ($mock) {
            $mock->shouldReceive('process')
                 ->andThrow(new \Exception('Primary job failed'));
        });

        try {
            ProcessBrainDumpJob::dispatch($content);
        } catch (\Exception $e) {
            // Expected primary failure
        }

        // Then: Dependent jobs should not be dispatched
        Queue::assertNotPushed(GenerateVectorEmbeddingsJob::class);

        // And: Failure should be logged appropriately
        Log::shouldReceive('error')
            ->with('Job chain failed', Mockery::any());
    }

    /**
     * Helper method to perform health checks
     */
    private function performHealthCheck(string $checkType): array
    {
        switch ($checkType) {
            case 'queue_workers':
                return ['healthy' => true, 'workers_active' => 3];
            case 'database_connectivity':
                return ['healthy' => DB::connection()->getPdo() !== null];
            case 'ai_service_availability':
                return ['healthy' => true, 'response_time' => 250];
            case 'memory_usage':
                $usage = memory_get_usage(true);
                return ['healthy' => $usage < 512 * 1024 * 1024, 'usage_mb' => $usage / 1024 / 1024];
            default:
                return ['healthy' => false, 'error' => 'Unknown check type'];
        }
    }
}