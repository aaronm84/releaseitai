<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\GenerateAiContentJob;
use App\Models\Content;
use App\Models\User;
use App\Services\AiService;
use App\Services\AiResponse;
use App\Exceptions\AiServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Mockery;

class GenerateAiContentJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Content $content;
    protected $mockAiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Generate release notes for API v2.0',
            'type' => 'ai_generation_request',
            'status' => 'pending'
        ]);

        $this->mockAiService = Mockery::mock(AiService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_be_instantiated_with_content()
    {
        $job = new GenerateAiContentJob($this->content, 'release_notes');

        $this->assertInstanceOf(GenerateAiContentJob::class, $job);
        $this->assertEquals($this->content->id, $job->content->id);
        $this->assertEquals('release_notes', $job->contentType);
    }

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new GenerateAiContentJob($this->content, 'summary');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_has_correct_queue_configuration()
    {
        $job = new GenerateAiContentJob($this->content, 'release_notes');

        $this->assertEquals('ai-content-generation', $job->queue);
        $this->assertEquals(240, $job->timeout); // 4 minutes
        $this->assertEquals(3, $job->tries);
        $this->assertIsArray($job->backoff);
        $this->assertEquals([60, 180, 300], $job->backoff); // Progressive backoff
    }

    /** @test */
    public function it_generates_release_notes_successfully()
    {
        $expectedResponse = new AiResponse(
            "# Release Notes v2.0\n\n## New Features\n- Enhanced API endpoints\n- Better error handling",
            150,
            0.002,
            ['model' => 'claude-3-5-sonnet']
        );

        $this->mockAiService
            ->shouldReceive('generateReleaseNotes')
            ->once()
            ->with([$this->content->content], 'technical')
            ->andReturn($expectedResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Starting AI content generation', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('AI content generation completed', Mockery::type('array'));

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'release_notes', ['audience' => 'technical']);
        $job->handle($this->mockAiService);

        $this->content->refresh();
        $this->assertEquals('processed', $this->content->status);
        $this->assertStringContains('Release Notes v2.0', $this->content->ai_summary);
    }

    /** @test */
    public function it_generates_morning_brief_successfully()
    {
        $briefData = [
            'emails' => ['Important client feedback received'],
            'tasks' => ['Review PRD for Q4 release'],
            'meetings' => ['Standup at 9 AM', 'Architecture review at 2 PM'],
            'releases' => ['v2.1 scheduled for next week']
        ];

        $expectedResponse = new AiResponse(
            "## Morning Brief\n\n**Priority Items:**\n- Review client feedback\n- Prepare for architecture review",
            100,
            0.001,
            ['model' => 'claude-3-5-sonnet']
        );

        $this->mockAiService
            ->shouldReceive('generateMorningBrief')
            ->once()
            ->with($briefData)
            ->andReturn($expectedResponse);

        Log::shouldReceive('info')->twice();

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'morning_brief', ['data' => $briefData]);
        $job->handle($this->mockAiService);

        $this->content->refresh();
        $this->assertEquals('processed', $this->content->status);
        $this->assertStringContains('Morning Brief', $this->content->ai_summary);
    }

    /** @test */
    public function it_generates_content_summary_successfully()
    {
        $expectedResponse = new AiResponse(
            "Key points: API improvements, performance enhancements, bug fixes for authentication module.",
            75,
            0.0008,
            ['model' => 'gpt-4o-mini']
        );

        $this->mockAiService
            ->shouldReceive('summarize')
            ->once()
            ->with($this->content->content, 200)
            ->andReturn($expectedResponse);

        Log::shouldReceive('info')->twice();

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'summary', ['max_length' => 200]);
        $job->handle($this->mockAiService);

        $this->content->refresh();
        $this->assertEquals('processed', $this->content->status);
        $this->assertStringContains('Key points', $this->content->ai_summary);
    }

    /** @test */
    public function it_handles_ai_service_timeout_with_retry()
    {
        $this->mockAiService
            ->shouldReceive('summarize')
            ->once()
            ->andThrow(new AiServiceException('Request timeout after 30 seconds'));

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('AI content generation timeout, will retry', Mockery::type('array'));

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'summary');

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockAiService);

        // Should not mark as failed on timeout - will retry
        $this->content->refresh();
        $this->assertNotEquals('failed', $this->content->status);
    }

    /** @test */
    public function it_handles_rate_limiting_gracefully()
    {
        $rateLimitException = new AiServiceException('Rate limit exceeded. Retry after: 60 seconds');

        $this->mockAiService
            ->shouldReceive('generateReleaseNotes')
            ->once()
            ->andThrow($rateLimitException);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('Rate limit hit during AI content generation', Mockery::type('array'));

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'release_notes');

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockAiService);

        // Content should remain in processing state for retry
        $this->content->refresh();
        $this->assertEquals('processing', $this->content->status);
    }

    /** @test */
    public function it_handles_cost_limit_exceeded()
    {
        $costException = new AiServiceException('Daily cost limit exceeded');

        $this->mockAiService
            ->shouldReceive('generateReleaseNotes')
            ->once()
            ->andThrow($costException);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->once()
            ->with('AI content generation failed due to cost limits', Mockery::type('array'));

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'release_notes');

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockAiService);

        $this->content->refresh();
        $this->assertEquals('failed', $this->content->status);
    }

    /** @test */
    public function it_caches_expensive_operations()
    {
        $cacheKey = 'ai_content:' . md5($this->content->content . 'summary');
        $cachedResponse = [
            'content' => 'Cached summary content',
            'tokens_used' => 50,
            'cost' => 0.0005
        ];

        Cache::shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn($cachedResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Using cached AI content generation result', Mockery::type('array'));

        $job = new GenerateAiContentJob($this->content, 'summary');
        $job->handle($this->mockAiService);

        $this->content->refresh();
        $this->assertEquals('processed', $this->content->status);
        $this->assertEquals('Cached summary content', $this->content->ai_summary);
    }

    /** @test */
    public function it_stores_successful_results_in_cache()
    {
        $cacheKey = 'ai_content:' . md5($this->content->content . 'summary');
        $expectedResponse = new AiResponse('Generated summary', 75, 0.001);

        $this->mockAiService
            ->shouldReceive('summarize')
            ->once()
            ->andReturn($expectedResponse);

        Cache::shouldReceive('get')->once()->andReturn(null);
        Cache::shouldReceive('put')
            ->once()
            ->with($cacheKey, Mockery::type('array'), 3600); // 1 hour cache

        Log::shouldReceive('info')->twice();

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'summary');
        $job->handle($this->mockAiService);
    }

    /** @test */
    public function it_tracks_token_usage_and_costs()
    {
        $expectedResponse = new AiResponse('Generated content', 150, 0.003);

        $this->mockAiService
            ->shouldReceive('generateReleaseNotes')
            ->once()
            ->andReturn($expectedResponse);

        Log::shouldReceive('info')->twice();

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'release_notes');
        $job->handle($this->mockAiService);

        $this->content->refresh();
        $metadata = $this->content->metadata;
        $this->assertEquals(150, $metadata['tokens_used']);
        $this->assertEquals(0.003, $metadata['generation_cost']);
        $this->assertArrayHasKey('generation_completed_at', $metadata);
    }

    /** @test */
    public function it_handles_invalid_content_type()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Invalid content type for AI generation', Mockery::type('array'));

        $job = new GenerateAiContentJob($this->content, 'invalid_type');

        $this->expectException(\InvalidArgumentException::class);
        $job->handle($this->mockAiService);

        $this->content->refresh();
        $this->assertEquals('failed', $this->content->status);
    }

    /** @test */
    public function it_implements_progressive_backoff_correctly()
    {
        $job = new GenerateAiContentJob($this->content, 'summary');

        $this->assertEquals([60, 180, 300], $job->backoff);
    }

    /** @test */
    public function it_fails_gracefully_after_max_retries()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('GenerateAiContentJob failed after max retries', Mockery::type('array'));

        $job = new GenerateAiContentJob($this->content, 'summary');
        $exception = new AiServiceException('Persistent failure');

        $job->failed($exception);

        $this->content->refresh();
        $this->assertEquals('failed', $this->content->status);
        $this->assertStringContains('Persistent failure', $this->content->metadata['error_message']);
    }

    /** @test */
    public function it_validates_content_before_processing()
    {
        $emptyContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => '', // Empty content
            'status' => 'pending'
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Cannot generate AI content for empty content', Mockery::type('array'));

        $job = new GenerateAiContentJob($emptyContent, 'summary');

        $this->expectException(\InvalidArgumentException::class);
        $job->handle($this->mockAiService);
    }

    /** @test */
    public function it_handles_concurrent_processing_prevention()
    {
        $lockKey = 'ai_generation_lock:' . $this->content->id;

        Cache::shouldReceive('add')
            ->once()
            ->with($lockKey, true, 300)
            ->andReturn(false); // Lock already exists

        Log::shouldReceive('info')
            ->once()
            ->with('AI content generation already in progress', Mockery::type('array'));

        $job = new GenerateAiContentJob($this->content, 'summary');
        $result = $job->handle($this->mockAiService);

        $this->assertNull($result);
    }

    /** @test */
    public function it_cleans_up_locks_on_completion()
    {
        $lockKey = 'ai_generation_lock:' . $this->content->id;
        $expectedResponse = new AiResponse('Generated content', 100, 0.001);

        Cache::shouldReceive('add')->once()->andReturn(true);
        Cache::shouldReceive('forget')->once()->with($lockKey);
        Cache::shouldReceive('get')->once()->andReturn(null);
        Cache::shouldReceive('put')->once();

        $this->mockAiService
            ->shouldReceive('summarize')
            ->once()
            ->andReturn($expectedResponse);

        Log::shouldReceive('info')->twice();

        $this->app->instance(AiService::class, $this->mockAiService);

        $job = new GenerateAiContentJob($this->content, 'summary');
        $job->handle($this->mockAiService);
    }
}