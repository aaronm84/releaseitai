<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\ProcessBrainDumpJob;
use App\Models\Content;
use App\Models\User;
use App\Services\AiEntityDetectionService;
use App\Services\AiService;
use App\Services\BrainDumpProcessor;
use App\Exceptions\AiServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Mockery;

class ProcessBrainDumpJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Content $content;
    protected $mockAiEntityDetectionService;
    protected $mockBrainDumpProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Sample brain dump content with tasks and stakeholders',
            'status' => 'pending'
        ]);

        $this->mockAiEntityDetectionService = Mockery::mock(AiEntityDetectionService::class);
        $this->mockBrainDumpProcessor = Mockery::mock(BrainDumpProcessor::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_be_instantiated_with_content()
    {
        $job = new ProcessBrainDumpJob($this->content);

        $this->assertInstanceOf(ProcessBrainDumpJob::class, $job);
        $this->assertEquals($this->content->id, $job->content->id);
    }

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new ProcessBrainDumpJob($this->content);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_has_correct_queue_configuration()
    {
        $job = new ProcessBrainDumpJob($this->content);

        $this->assertEquals('ai-processing', $job->queue);
        $this->assertEquals(300, $job->timeout); // 5 minutes
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->backoff); // 2 minutes exponential backoff
    }

    /** @test */
    public function it_processes_brain_dump_successfully()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Starting brain dump processing for content ID: ' . $this->content->id);

        Log::shouldReceive('info')
            ->once()
            ->with('Brain dump processing completed for content ID: ' . $this->content->id);

        $this->mockBrainDumpProcessor
            ->shouldReceive('process')
            ->once()
            ->with($this->content)
            ->andReturn([
                'entities' => [
                    'stakeholders' => [['name' => 'John Doe', 'confidence' => 0.9]],
                    'workstreams' => [['name' => 'Project Alpha', 'confidence' => 0.8]]
                ],
                'action_items' => [['text' => 'Review API design', 'priority' => 'high']],
                'summary' => 'Brain dump processed successfully'
            ]);

        $this->app->instance(BrainDumpProcessor::class, $this->mockBrainDumpProcessor);

        $job = new ProcessBrainDumpJob($this->content);
        $job->handle($this->mockBrainDumpProcessor);

        $this->content->refresh();
        $this->assertEquals('processed', $this->content->status);
        $this->assertNotNull($this->content->processed_at);
    }

    /** @test */
    public function it_handles_ai_service_timeout_gracefully()
    {
        $this->mockBrainDumpProcessor
            ->shouldReceive('process')
            ->once()
            ->with($this->content)
            ->andThrow(new AiServiceException('Request timeout'));

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->once()
            ->with('Brain dump processing failed for content ID: ' . $this->content->id, Mockery::type('array'));

        $this->app->instance(BrainDumpProcessor::class, $this->mockBrainDumpProcessor);

        $job = new ProcessBrainDumpJob($this->content);

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockBrainDumpProcessor);

        $this->content->refresh();
        $this->assertEquals('failed', $this->content->status);
    }

    /** @test */
    public function it_implements_exponential_backoff_on_retry()
    {
        $job = new ProcessBrainDumpJob($this->content);

        // First attempt: 0 seconds delay
        $this->assertEquals(0, $job->backoff);

        // Simulate attempts to test backoff
        $job->attempts = 1;
        $this->assertEquals(120, $job->backoff); // 2 minutes

        $job->attempts = 2;
        $this->assertEquals(240, $job->backoff); // 4 minutes
    }

    /** @test */
    public function it_handles_rate_limiting_from_ai_provider()
    {
        $rateLimitException = new AiServiceException('Rate limit exceeded. Please try again later.');

        $this->mockBrainDumpProcessor
            ->shouldReceive('process')
            ->once()
            ->with($this->content)
            ->andThrow($rateLimitException);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('Rate limit hit for brain dump processing', Mockery::type('array'));

        $this->app->instance(BrainDumpProcessor::class, $this->mockBrainDumpProcessor);

        $job = new ProcessBrainDumpJob($this->content);
        $job->shouldRetryOnRateLimit = true;

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockBrainDumpProcessor);

        // Should not mark content as failed on rate limit - should retry
        $this->content->refresh();
        $this->assertNotEquals('failed', $this->content->status);
    }

    /** @test */
    public function it_updates_content_status_during_processing()
    {
        $this->mockBrainDumpProcessor
            ->shouldReceive('process')
            ->once()
            ->with($this->content)
            ->andReturnUsing(function($content) {
                // Verify content status is updated to processing
                $content->refresh();
                $this->assertEquals('processing', $content->status);

                return [
                    'entities' => [],
                    'action_items' => [],
                    'summary' => 'Processed'
                ];
            });

        $this->app->instance(BrainDumpProcessor::class, $this->mockBrainDumpProcessor);

        Log::shouldReceive('info')->twice();

        $job = new ProcessBrainDumpJob($this->content);
        $job->handle($this->mockBrainDumpProcessor);
    }

    /** @test */
    public function it_logs_processing_progress_appropriately()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Starting brain dump processing for content ID: ' . $this->content->id);

        Log::shouldReceive('info')
            ->once()
            ->with('Brain dump processing completed for content ID: ' . $this->content->id);

        $this->mockBrainDumpProcessor
            ->shouldReceive('process')
            ->once()
            ->andReturn(['entities' => [], 'action_items' => [], 'summary' => 'Test']);

        $this->app->instance(BrainDumpProcessor::class, $this->mockBrainDumpProcessor);

        $job = new ProcessBrainDumpJob($this->content);
        $job->handle($this->mockBrainDumpProcessor);
    }

    /** @test */
    public function it_cleans_up_resources_on_failure()
    {
        $this->mockBrainDumpProcessor
            ->shouldReceive('process')
            ->once()
            ->andThrow(new \Exception('Processing failed'));

        Cache::shouldReceive('forget')
            ->once()
            ->with('brain_dump_lock:' . $this->content->id);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $this->app->instance(BrainDumpProcessor::class, $this->mockBrainDumpProcessor);

        $job = new ProcessBrainDumpJob($this->content);

        $this->expectException(\Exception::class);
        $job->handle($this->mockBrainDumpProcessor);
    }

    /** @test */
    public function it_handles_failed_method_correctly()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('ProcessBrainDumpJob job failed for content ID: ' . $this->content->id, Mockery::type('array'));

        $job = new ProcessBrainDumpJob($this->content);
        $exception = new \Exception('Test exception');

        $job->failed($exception);

        $this->content->refresh();
        $this->assertEquals('failed', $this->content->status);
    }

    /** @test */
    public function it_prevents_duplicate_processing_with_locks()
    {
        $lockKey = 'brain_dump_lock:' . $this->content->id;

        Cache::shouldReceive('add')
            ->once()
            ->with($lockKey, true, 300)
            ->andReturn(false); // Lock already exists

        Log::shouldReceive('warning')
            ->once()
            ->with('Brain dump already being processed for content ID: ' . $this->content->id);

        $job = new ProcessBrainDumpJob($this->content);
        $result = $job->handle($this->mockBrainDumpProcessor);

        // Should exit early without processing
        $this->assertNull($result);
    }

    /** @test */
    public function it_serializes_models_correctly()
    {
        $job = new ProcessBrainDumpJob($this->content);

        // Test that the job can be serialized and unserialized
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals($this->content->id, $unserialized->content->id);
    }

    /** @test */
    public function it_has_correct_queue_priority_for_brain_dumps()
    {
        $job = new ProcessBrainDumpJob($this->content);

        // Brain dumps should be high priority
        $this->assertEquals('high', $job->queue);
    }

    /** @test */
    public function it_tracks_processing_metrics()
    {
        $startTime = now();

        $this->mockBrainDumpProcessor
            ->shouldReceive('process')
            ->once()
            ->andReturn(['entities' => [], 'action_items' => [], 'summary' => 'Test']);

        Cache::shouldReceive('put')
            ->once()
            ->with('brain_dump_metrics:' . $this->content->id, Mockery::type('array'), 3600);

        Log::shouldReceive('info')->twice();

        $this->app->instance(BrainDumpProcessor::class, $this->mockBrainDumpProcessor);

        $job = new ProcessBrainDumpJob($this->content);
        $job->handle($this->mockBrainDumpProcessor);
    }
}