<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\GenerateVectorEmbeddingsJob;
use App\Models\Content;
use App\Models\Embedding;
use App\Models\User;
use App\Services\AiService;
use App\Services\EmbeddingService;
use App\Exceptions\AiServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;

class GenerateVectorEmbeddingsJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Content $content;
    protected $mockEmbeddingService;
    protected $mockAiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'This is sample content for vector embedding generation',
            'status' => 'processed'
        ]);

        $this->mockEmbeddingService = Mockery::mock(EmbeddingService::class);
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
        $job = new GenerateVectorEmbeddingsJob($this->content);

        $this->assertInstanceOf(GenerateVectorEmbeddingsJob::class, $job);
        $this->assertEquals($this->content->id, $job->content->id);
    }

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new GenerateVectorEmbeddingsJob($this->content);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_has_correct_queue_configuration()
    {
        $job = new GenerateVectorEmbeddingsJob($this->content);

        $this->assertEquals('ai-embeddings', $job->queue);
        $this->assertEquals(120, $job->timeout); // 2 minutes
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 90, 180], $job->backoff);
    }

    /** @test */
    public function it_generates_embeddings_successfully()
    {
        $mockEmbedding = [
            0.1, 0.2, 0.3, 0.4, 0.5, // Simplified 5-dimensional vector
            // In reality, would be 1536 dimensions for OpenAI embeddings
        ];

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->with($this->content->content)
            ->andReturn($mockEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->with($this->content, $mockEmbedding, 'text-embedding-ada-002')
            ->andReturn(Mockery::mock(Embedding::class));

        Log::shouldReceive('info')
            ->once()
            ->with('Starting vector embedding generation', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Vector embedding generation completed', Mockery::type('array'));

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $job->handle($this->mockEmbeddingService);

        $this->content->refresh();
        $this->assertArrayHasKey('embedding_generated_at', $this->content->metadata);
    }

    /** @test */
    public function it_handles_batch_embedding_generation()
    {
        $contents = collect([
            $this->content,
            Content::factory()->create(['user_id' => $this->user->id]),
            Content::factory()->create(['user_id' => $this->user->id])
        ]);

        $mockEmbeddings = [
            [0.1, 0.2, 0.3],
            [0.4, 0.5, 0.6],
            [0.7, 0.8, 0.9]
        ];

        $this->mockEmbeddingService
            ->shouldReceive('generateBatchEmbeddings')
            ->once()
            ->with($contents->pluck('content')->toArray())
            ->andReturn($mockEmbeddings);

        $this->mockEmbeddingService
            ->shouldReceive('storeBatchEmbeddings')
            ->once()
            ->with($contents, $mockEmbeddings)
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Starting batch vector embedding generation', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Batch vector embedding generation completed', Mockery::type('array'));

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content, $contents);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_handles_ai_service_rate_limiting()
    {
        $rateLimitException = new AiServiceException('Rate limit exceeded for embeddings API');

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->andThrow($rateLimitException);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('Rate limit hit during embedding generation', Mockery::type('array'));

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockEmbeddingService);

        // Should not mark as failed on rate limit - will retry
        $this->content->refresh();
        $this->assertArrayNotHasKey('embedding_failed_at', $this->content->metadata ?? []);
    }

    /** @test */
    public function it_handles_embedding_api_timeouts()
    {
        $timeoutException = new AiServiceException('Request timeout after 30 seconds');

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->andThrow($timeoutException);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('Embedding generation timeout, will retry', Mockery::type('array'));

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_prevents_duplicate_embedding_generation()
    {
        $existingEmbedding = Embedding::factory()->create([
            'content_id' => $this->content->id,
            'model' => 'text-embedding-ada-002'
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Embedding already exists for content', Mockery::type('array'));

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $result = $job->handle($this->mockEmbeddingService);

        $this->assertNull($result);
    }

    /** @test */
    public function it_chunks_large_content_appropriately()
    {
        $largeContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => str_repeat('This is a large content block. ', 1000), // ~30KB
            'status' => 'processed'
        ]);

        $this->mockEmbeddingService
            ->shouldReceive('chunkContent')
            ->once()
            ->with($largeContent->content, 8000) // OpenAI token limit
            ->andReturn(['chunk1', 'chunk2', 'chunk3']);

        $this->mockEmbeddingService
            ->shouldReceive('generateBatchEmbeddings')
            ->once()
            ->with(['chunk1', 'chunk2', 'chunk3'])
            ->andReturn([[0.1, 0.2], [0.3, 0.4], [0.5, 0.6]]);

        $this->mockEmbeddingService
            ->shouldReceive('storeChunkedEmbeddings')
            ->once()
            ->with($largeContent, Mockery::type('array'))
            ->andReturn(true);

        Log::shouldReceive('info')->twice();

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($largeContent);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_handles_vector_database_storage_failures()
    {
        $mockEmbedding = [0.1, 0.2, 0.3];

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->andReturn($mockEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->andThrow(new \Exception('Vector database connection failed'));

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->once()
            ->with('Failed to store vector embedding', Mockery::type('array'));

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);

        $this->expectException(\Exception::class);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_implements_embedding_caching()
    {
        $contentHash = md5($this->content->content);
        $cacheKey = "embedding:text-embedding-ada-002:{$contentHash}";
        $cachedEmbedding = [0.1, 0.2, 0.3, 0.4, 0.5];

        Cache::shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn($cachedEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->with($this->content, $cachedEmbedding, 'text-embedding-ada-002')
            ->andReturn(Mockery::mock(Embedding::class));

        Log::shouldReceive('info')
            ->once()
            ->with('Using cached embedding for content', Mockery::type('array'));

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_stores_embeddings_in_cache_after_generation()
    {
        $mockEmbedding = [0.1, 0.2, 0.3];
        $contentHash = md5($this->content->content);
        $cacheKey = "embedding:text-embedding-ada-002:{$contentHash}";

        Cache::shouldReceive('get')->once()->andReturn(null);
        Cache::shouldReceive('put')
            ->once()
            ->with($cacheKey, $mockEmbedding, 86400); // 24 hours

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->andReturn($mockEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->andReturn(Mockery::mock(Embedding::class));

        Log::shouldReceive('info')->twice();

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_tracks_embedding_generation_metrics()
    {
        $mockEmbedding = [0.1, 0.2, 0.3];
        $startTime = microtime(true);

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->andReturn($mockEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->andReturn(Mockery::mock(Embedding::class));

        Log::shouldReceive('info')->twice();

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $job->handle($this->mockEmbeddingService);

        $this->content->refresh();
        $metadata = $this->content->metadata;
        $this->assertArrayHasKey('embedding_generated_at', $metadata);
        $this->assertArrayHasKey('embedding_generation_duration_ms', $metadata);
        $this->assertEquals(count($mockEmbedding), $metadata['embedding_dimensions']);
    }

    /** @test */
    public function it_handles_concurrent_embedding_generation_prevention()
    {
        $lockKey = 'embedding_generation_lock:' . $this->content->id;

        Cache::shouldReceive('add')
            ->once()
            ->with($lockKey, true, 180)
            ->andReturn(false); // Lock already exists

        Log::shouldReceive('info')
            ->once()
            ->with('Embedding generation already in progress', Mockery::type('array'));

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $result = $job->handle($this->mockEmbeddingService);

        $this->assertNull($result);
    }

    /** @test */
    public function it_validates_content_before_embedding_generation()
    {
        $emptyContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => '', // Empty content
            'status' => 'processed'
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Cannot generate embedding for empty content', Mockery::type('array'));

        $job = new GenerateVectorEmbeddingsJob($emptyContent);

        $this->expectException(\InvalidArgumentException::class);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_supports_multiple_embedding_models()
    {
        $mockEmbedding = [0.1, 0.2, 0.3];

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->with($this->content->content, 'text-embedding-3-large')
            ->andReturn($mockEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->with($this->content, $mockEmbedding, 'text-embedding-3-large')
            ->andReturn(Mockery::mock(Embedding::class));

        Log::shouldReceive('info')->twice();

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content, null, 'text-embedding-3-large');
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_cleans_up_locks_on_completion()
    {
        $lockKey = 'embedding_generation_lock:' . $this->content->id;
        $mockEmbedding = [0.1, 0.2, 0.3];

        Cache::shouldReceive('add')->once()->andReturn(true);
        Cache::shouldReceive('forget')->once()->with($lockKey);
        Cache::shouldReceive('get')->once()->andReturn(null);
        Cache::shouldReceive('put')->once();

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->andReturn($mockEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->andReturn(Mockery::mock(Embedding::class));

        Log::shouldReceive('info')->twice();

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $job->handle($this->mockEmbeddingService);
    }

    /** @test */
    public function it_handles_failed_method_correctly()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('GenerateVectorEmbeddingsJob failed', Mockery::type('array'));

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $exception = new \Exception('Embedding generation failed');

        $job->failed($exception);

        $this->content->refresh();
        $metadata = $this->content->metadata ?? [];
        $this->assertArrayHasKey('embedding_failed_at', $metadata);
        $this->assertStringContains('Embedding generation failed', $metadata['error_message']);
    }

    /** @test */
    public function it_updates_similarity_search_indexes_after_embedding()
    {
        $mockEmbedding = [0.1, 0.2, 0.3];

        $this->mockEmbeddingService
            ->shouldReceive('generateEmbedding')
            ->once()
            ->andReturn($mockEmbedding);

        $this->mockEmbeddingService
            ->shouldReceive('storeEmbedding')
            ->once()
            ->andReturn(Mockery::mock(Embedding::class));

        $this->mockEmbeddingService
            ->shouldReceive('updateSimilaritySearchIndex')
            ->once()
            ->with($this->content->id)
            ->andReturn(true);

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('info')
            ->once()
            ->with('Updated similarity search index for content', Mockery::type('array'));

        $this->app->instance(EmbeddingService::class, $this->mockEmbeddingService);

        $job = new GenerateVectorEmbeddingsJob($this->content);
        $job->handle($this->mockEmbeddingService);
    }
}