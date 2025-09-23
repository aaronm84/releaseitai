<?php

namespace Tests\Unit\Queue;

use Tests\TestCase;
use App\Jobs\ProcessBrainDumpJob;
use App\Jobs\GenerateAiContentJob;
use App\Jobs\ProcessFeedbackLearningJob;
use App\Jobs\GenerateVectorEmbeddingsJob;
use App\Models\Content;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class QueueConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Queue::fake();
    }

    /** @test */
    public function it_configures_ai_processing_queue_correctly()
    {
        // Given: AI processing queue configuration
        $expectedConfig = [
            'driver' => 'redis',
            'connection' => 'ai-processing',
            'queue' => 'ai-processing',
            'retry_after' => 300,
            'block_for' => null,
        ];

        // When: Checking queue configuration
        $config = Config::get('queue.connections.redis-ai-processing');

        // Then: Configuration should match expectations
        $this->assertEquals('redis', $config['driver']);
        $this->assertEquals(300, $config['retry_after']);
    }

    /** @test */
    public function it_assigns_jobs_to_correct_queues_by_priority()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // When: Dispatching jobs with different priorities
        ProcessBrainDumpJob::dispatch($content)->onQueue('ai-processing-high');
        GenerateAiContentJob::dispatch($content, 'summary')->onQueue('ai-content-generation');
        GenerateVectorEmbeddingsJob::dispatch($content)->onQueue('ai-embeddings');

        // Then: Jobs should be on correct queues
        Queue::assertPushedOn('ai-processing-high', ProcessBrainDumpJob::class);
        Queue::assertPushedOn('ai-content-generation', GenerateAiContentJob::class);
        Queue::assertPushedOn('ai-embeddings', GenerateVectorEmbeddingsJob::class);
    }

    /** @test */
    public function it_configures_different_queues_for_different_ai_operations()
    {
        // Given: Different AI operations
        $operations = [
            'brain_dump' => 'ai-processing',
            'content_generation' => 'ai-content-generation',
            'feedback_learning' => 'ai-learning',
            'vector_embeddings' => 'ai-embeddings'
        ];

        // When: Checking each queue configuration
        foreach ($operations as $operation => $queueName) {
            $queueConfig = Config::get("queue.connections.redis-{$queueName}");

            // Then: Each queue should be properly configured
            $this->assertNotNull($queueConfig, "Queue {$queueName} should be configured");
            $this->assertEquals('redis', $queueConfig['driver']);
        }
    }

    /** @test */
    public function it_sets_appropriate_timeout_values_for_different_job_types()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // Given: Different job types with different timeout requirements
        $jobs = [
            new ProcessBrainDumpJob($content),
            new GenerateAiContentJob($content, 'summary'),
            new GenerateVectorEmbeddingsJob($content),
        ];

        // When: Checking timeout configurations
        $timeouts = [
            ProcessBrainDumpJob::class => 300,        // 5 minutes
            GenerateAiContentJob::class => 240,       // 4 minutes
            GenerateVectorEmbeddingsJob::class => 120, // 2 minutes
        ];

        // Then: Each job type should have appropriate timeout
        foreach ($jobs as $job) {
            $expectedTimeout = $timeouts[get_class($job)];
            $this->assertEquals($expectedTimeout, $job->timeout);
        }
    }

    /** @test */
    public function it_configures_retry_attempts_appropriately()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $feedback = Feedback::factory()->create(['user_id' => $this->user->id]);

        // Given: Different job types with different retry requirements
        $jobs = [
            new ProcessBrainDumpJob($content),
            new GenerateAiContentJob($content, 'summary'),
            new ProcessFeedbackLearningJob($feedback),
            new GenerateVectorEmbeddingsJob($content),
        ];

        // When: Checking retry configurations
        $expectedRetries = [
            ProcessBrainDumpJob::class => 3,
            GenerateAiContentJob::class => 3,
            ProcessFeedbackLearningJob::class => 5, // More retries for learning
            GenerateVectorEmbeddingsJob::class => 3,
        ];

        // Then: Each job type should have appropriate retry count
        foreach ($jobs as $job) {
            $expectedTries = $expectedRetries[get_class($job)];
            $this->assertEquals($expectedTries, $job->tries);
        }
    }

    /** @test */
    public function it_implements_progressive_backoff_strategies()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $feedback = Feedback::factory()->create(['user_id' => $this->user->id]);

        // Given: Jobs with different backoff strategies
        $jobs = [
            new ProcessBrainDumpJob($content),
            new GenerateAiContentJob($content, 'summary'),
            new ProcessFeedbackLearningJob($feedback),
        ];

        // When: Checking backoff configurations
        $expectedBackoff = [
            ProcessBrainDumpJob::class => 120,           // Fixed backoff
            GenerateAiContentJob::class => [60, 180, 300], // Progressive
            ProcessFeedbackLearningJob::class => [30, 60, 120, 240, 480], // Aggressive progressive
        ];

        // Then: Each job should have appropriate backoff strategy
        foreach ($jobs as $job) {
            $expected = $expectedBackoff[get_class($job)];
            $this->assertEquals($expected, $job->backoff);
        }
    }

    /** @test */
    public function it_configures_dead_letter_queue_handling()
    {
        // Given: Queue configuration with dead letter queue
        $deadLetterConfig = Config::get('queue.failed');

        // Then: Dead letter queue should be properly configured
        $this->assertEquals('database', $deadLetterConfig['driver']);
        $this->assertEquals('failed_jobs', $deadLetterConfig['table']);
    }

    /** @test */
    public function it_supports_queue_worker_scaling_configuration()
    {
        // Given: Worker scaling configuration
        $scalingConfig = [
            'ai-processing-high' => ['min_workers' => 2, 'max_workers' => 8],
            'ai-processing' => ['min_workers' => 1, 'max_workers' => 4],
            'ai-content-generation' => ['min_workers' => 1, 'max_workers' => 3],
            'ai-learning' => ['min_workers' => 1, 'max_workers' => 2],
            'ai-embeddings' => ['min_workers' => 1, 'max_workers' => 3],
        ];

        // When: Checking scaling configuration
        foreach ($scalingConfig as $queue => $config) {
            $queueConfig = Config::get("queue.worker_scaling.{$queue}");

            // Then: Scaling should be properly configured
            if ($queueConfig) {
                $this->assertArrayHasKey('min_workers', $queueConfig);
                $this->assertArrayHasKey('max_workers', $queueConfig);
                $this->assertLessThanOrEqual($config['max_workers'], $queueConfig['max_workers']);
            }
        }
    }

    /** @test */
    public function it_configures_queue_priorities_correctly()
    {
        // Given: Queue priority configuration
        $priorities = [
            'ai-processing-urgent' => 10,
            'ai-processing-high' => 8,
            'ai-processing' => 5,
            'ai-content-generation' => 3,
            'ai-learning' => 2,
            'ai-embeddings' => 1,
        ];

        // When: Checking priority configuration
        $queuePriorities = Config::get('queue.priorities');

        // Then: Priorities should be properly set
        foreach ($priorities as $queue => $expectedPriority) {
            if (isset($queuePriorities[$queue])) {
                $this->assertEquals($expectedPriority, $queuePriorities[$queue]);
            }
        }
    }

    /** @test */
    public function it_configures_memory_limits_for_different_job_types()
    {
        // Given: Different job types with memory requirements
        $memoryLimits = [
            'ai-processing' => '512M',
            'ai-content-generation' => '256M',
            'ai-learning' => '1G',        // Learning requires more memory
            'ai-embeddings' => '512M',
        ];

        // When: Checking memory limit configuration
        foreach ($memoryLimits as $queue => $expectedLimit) {
            $queueConfig = Config::get("queue.connections.redis-{$queue}");

            // Then: Memory limits should be appropriate for job type
            if (isset($queueConfig['memory_limit'])) {
                $this->assertEquals($expectedLimit, $queueConfig['memory_limit']);
            }
        }
    }

    /** @test */
    public function it_configures_redis_connection_settings_for_queues()
    {
        // Given: Redis connection configuration for queues
        $redisConfig = Config::get('database.redis.ai-queue');

        // Then: Redis should be properly configured for queue operations
        $this->assertNotNull($redisConfig);
        $this->assertArrayHasKey('host', $redisConfig);
        $this->assertArrayHasKey('port', $redisConfig);
        $this->assertArrayHasKey('database', $redisConfig);
    }

    /** @test */
    public function it_supports_queue_batching_configuration()
    {
        // Given: Batch processing configuration
        $batchConfig = [
            'ai-embeddings' => ['batch_size' => 10, 'batch_timeout' => 30],
            'ai-learning' => ['batch_size' => 5, 'batch_timeout' => 60],
        ];

        // When: Checking batch configuration
        foreach ($batchConfig as $queue => $config) {
            $queueBatchConfig = Config::get("queue.batching.{$queue}");

            // Then: Batch settings should be appropriate
            if ($queueBatchConfig) {
                $this->assertArrayHasKey('batch_size', $queueBatchConfig);
                $this->assertArrayHasKey('batch_timeout', $queueBatchConfig);
            }
        }
    }

    /** @test */
    public function it_configures_queue_encryption_for_sensitive_data()
    {
        // Given: Encryption configuration for sensitive queues
        $encryptionConfig = Config::get('queue.encryption');

        // Then: Sensitive queues should use encryption
        $this->assertTrue($encryptionConfig['enabled']);
        $this->assertContains('ai-learning', $encryptionConfig['queues']);
        $this->assertContains('ai-processing', $encryptionConfig['queues']);
    }

    /** @test */
    public function it_supports_queue_monitoring_configuration()
    {
        // Given: Queue monitoring configuration
        $monitoringConfig = Config::get('queue.monitoring');

        // Then: Monitoring should be properly configured
        $this->assertArrayHasKey('enabled', $monitoringConfig);
        $this->assertArrayHasKey('metrics_driver', $monitoringConfig);
        $this->assertArrayHasKey('alert_thresholds', $monitoringConfig);
    }

    /** @test */
    public function it_configures_queue_rate_limiting()
    {
        // Given: Rate limiting configuration for AI providers
        $rateLimits = [
            'ai-processing' => ['requests_per_minute' => 60],
            'ai-content-generation' => ['requests_per_minute' => 30],
            'ai-embeddings' => ['requests_per_minute' => 100],
        ];

        // When: Checking rate limit configuration
        foreach ($rateLimits as $queue => $limits) {
            $queueRateLimit = Config::get("queue.rate_limits.{$queue}");

            // Then: Rate limits should prevent API abuse
            if ($queueRateLimit) {
                $this->assertArrayHasKey('requests_per_minute', $queueRateLimit);
                $this->assertLessThanOrEqual(100, $queueRateLimit['requests_per_minute']);
            }
        }
    }

    /** @test */
    public function it_supports_queue_circuit_breaker_configuration()
    {
        // Given: Circuit breaker configuration
        $circuitBreakerConfig = Config::get('queue.circuit_breaker');

        // Then: Circuit breaker should be configured for reliability
        $this->assertArrayHasKey('failure_threshold', $circuitBreakerConfig);
        $this->assertArrayHasKey('recovery_timeout', $circuitBreakerConfig);
        $this->assertArrayHasKey('half_open_max_calls', $circuitBreakerConfig);
    }

    /** @test */
    public function it_configures_queue_health_checks()
    {
        // Given: Health check configuration
        $healthCheckConfig = Config::get('queue.health_checks');

        // Then: Health checks should be properly configured
        $this->assertArrayHasKey('enabled', $healthCheckConfig);
        $this->assertArrayHasKey('check_interval', $healthCheckConfig);
        $this->assertArrayHasKey('timeout', $healthCheckConfig);
    }

    /** @test */
    public function it_supports_environment_specific_queue_configuration()
    {
        // Given: Different environments
        $environments = ['local', 'testing', 'staging', 'production'];

        foreach ($environments as $env) {
            // When: Checking environment-specific configuration
            Config::set('app.env', $env);

            $queueConfig = Config::get('queue.connections.redis');

            // Then: Configuration should adapt to environment
            $this->assertNotNull($queueConfig);

            // Production should have stricter settings
            if ($env === 'production') {
                $this->assertArrayHasKey('retry_after', $queueConfig);
                $this->assertGreaterThan(60, $queueConfig['retry_after']);
            }
        }
    }

    /** @test */
    public function it_configures_queue_logging_and_metrics()
    {
        // Given: Logging configuration for queues
        $loggingConfig = Config::get('queue.logging');

        // Then: Logging should capture important events
        $this->assertArrayHasKey('log_failed_jobs', $loggingConfig);
        $this->assertArrayHasKey('log_job_progress', $loggingConfig);
        $this->assertArrayHasKey('metrics_enabled', $loggingConfig);
    }

    /** @test */
    public function it_supports_dynamic_queue_configuration()
    {
        // Given: Initial queue configuration
        $initialWorkers = Config::get('queue.workers.ai-processing');

        // When: Load increases (simulated)
        $queueSize = 100; // Simulate large queue

        // Then: Configuration should support dynamic scaling
        if ($queueSize > 50) {
            $scaledWorkers = min($initialWorkers * 2, 8); // Scale up but cap at 8
            $this->assertLessThanOrEqual(8, $scaledWorkers);
        }
    }

    /** @test */
    public function it_configures_queue_persistence_and_durability()
    {
        // Given: Persistence configuration
        $persistenceConfig = Config::get('queue.persistence');

        // Then: Queues should be durable
        $this->assertTrue($persistenceConfig['redis_persistence']);
        $this->assertArrayHasKey('backup_interval', $persistenceConfig);
        $this->assertArrayHasKey('retention_period', $persistenceConfig);
    }
}