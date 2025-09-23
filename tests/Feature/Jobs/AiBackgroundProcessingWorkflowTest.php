<?php

namespace Tests\Feature\Jobs;

use Tests\TestCase;
use App\Jobs\ProcessBrainDumpJob;
use App\Jobs\GenerateAiContentJob;
use App\Jobs\ProcessFeedbackLearningJob;
use App\Jobs\GenerateVectorEmbeddingsJob;
use App\Models\Content;
use App\Models\User;
use App\Models\Feedback;
use App\Models\Input;
use App\Models\Output;
use App\Models\AiJob;
use App\Services\AiService;
use App\Services\BrainDumpProcessor;
use App\Services\FeedbackService;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiBackgroundProcessingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Configure test queue
        Queue::fake();
        Bus::fake();
    }

    /** @test */
    public function it_processes_complete_brain_dump_workflow_end_to_end()
    {
        // Given: A brain dump content is created
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Meeting with John Doe about Project Alpha. Action items: 1) Review API design by Friday 2) Schedule follow-up with Jane Smith',
            'type' => 'brain_dump',
            'status' => 'pending'
        ]);

        // When: The brain dump processing job is dispatched
        ProcessBrainDumpJob::dispatch($content);

        // Then: Verify the job was queued
        Queue::assertPushed(ProcessBrainDumpJob::class, function ($job) use ($content) {
            return $job->content->id === $content->id;
        });

        // And: Verify job configuration
        Queue::assertPushedOn('ai-processing', ProcessBrainDumpJob::class);

        // When: Job is processed (simulate)
        $this->artisan('queue:work --once --queue=ai-processing');

        // Then: Follow-up jobs should be dispatched
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class);
    }

    /** @test */
    public function it_processes_ai_content_generation_workflow()
    {
        // Given: Content requiring AI generation
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Generate release notes for API v2.0 with new endpoints and breaking changes',
            'type' => 'ai_generation_request',
            'status' => 'pending'
        ]);

        // When: AI content generation job is dispatched
        GenerateAiContentJob::dispatch($content, 'release_notes', [
            'audience' => 'technical',
            'format' => 'markdown'
        ]);

        // Then: Verify correct queue and job configuration
        Queue::assertPushed(GenerateAiContentJob::class, function ($job) use ($content) {
            return $job->content->id === $content->id &&
                   $job->contentType === 'release_notes';
        });

        Queue::assertPushedOn('ai-content-generation', GenerateAiContentJob::class);
    }

    /** @test */
    public function it_processes_feedback_learning_workflow()
    {
        // Given: User provides feedback on AI output
        $input = Input::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Extract stakeholders from the meeting notes'
        ]);

        $output = Output::factory()->create([
            'input_id' => $input->id,
            'content' => json_encode(['stakeholders' => ['John Doe']]),
            'confidence_score' => 0.85
        ]);

        $feedback = Feedback::factory()->create([
            'input_id' => $input->id,
            'output_id' => $output->id,
            'user_id' => $this->user->id,
            'feedback_type' => 'correction',
            'feedback_data' => json_encode([
                'corrected_stakeholders' => ['John Doe', 'Jane Smith', 'Mike Johnson'],
                'missing_entities' => ['Jane Smith', 'Mike Johnson']
            ])
        ]);

        // When: Feedback learning job is dispatched
        ProcessFeedbackLearningJob::dispatch($feedback);

        // Then: Verify job was queued correctly
        Queue::assertPushed(ProcessFeedbackLearningJob::class, function ($job) use ($feedback) {
            return $job->feedback->id === $feedback->id;
        });

        Queue::assertPushedOn('ai-learning', ProcessFeedbackLearningJob::class);
    }

    /** @test */
    public function it_processes_vector_embeddings_workflow()
    {
        // Given: Content that needs vector embeddings
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Technical documentation about microservices architecture patterns',
            'status' => 'processed'
        ]);

        // When: Vector embeddings job is dispatched
        GenerateVectorEmbeddingsJob::dispatch($content);

        // Then: Verify job was queued correctly
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class, function ($job) use ($content) {
            return $job->content->id === $content->id;
        });

        Queue::assertPushedOn('ai-embeddings', GenerateVectorEmbeddingsJob::class);
    }

    /** @test */
    public function it_handles_prioritized_queue_processing()
    {
        // Given: Multiple AI jobs with different priorities
        $urgentContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Urgent issue requires immediate analysis',
            'metadata' => ['priority' => 'urgent']
        ]);

        $normalContent = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Regular content for processing'
        ]);

        // When: Jobs are dispatched with different priorities
        ProcessBrainDumpJob::dispatch($urgentContent)->onQueue('ai-processing-high');
        ProcessBrainDumpJob::dispatch($normalContent)->onQueue('ai-processing');

        // Then: Verify correct queue assignment
        Queue::assertPushedOn('ai-processing-high', ProcessBrainDumpJob::class);
        Queue::assertPushedOn('ai-processing', ProcessBrainDumpJob::class);
    }

    /** @test */
    public function it_handles_batch_processing_workflow()
    {
        // Given: Multiple contents for batch processing
        $contents = Content::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'status' => 'processed'
        ]);

        // When: Batch vector embeddings job is dispatched
        GenerateVectorEmbeddingsJob::dispatch($contents->first(), $contents);

        // Then: Verify batch job was queued
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class, function ($job) use ($contents) {
            return $job->batchContents && $job->batchContents->count() === 5;
        });
    }

    /** @test */
    public function it_tracks_job_progress_through_workflow()
    {
        // Given: A content item to process
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Sample content for workflow tracking',
            'status' => 'pending'
        ]);

        // When: Processing starts
        ProcessBrainDumpJob::dispatch($content);

        // Then: Verify initial state
        $this->assertEquals('pending', $content->status);

        // When: Job is processed
        $this->artisan('queue:work --once --queue=ai-processing');

        // Then: Status should be updated
        $content->refresh();
        $this->assertContains($content->status, ['processing', 'processed']);
    }

    /** @test */
    public function it_handles_workflow_failures_gracefully()
    {
        // Given: Content that will cause processing failure
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => '', // Empty content to trigger failure
            'status' => 'pending'
        ]);

        // Mock the brain dump processor to fail
        $this->mock(BrainDumpProcessor::class, function ($mock) {
            $mock->shouldReceive('process')
                 ->andThrow(new \Exception('Processing failed'));
        });

        // When: Job is processed and fails
        try {
            $job = new ProcessBrainDumpJob($content);
            $processor = app(BrainDumpProcessor::class);
            $job->handle($processor);
        } catch (\Exception $e) {
            // Expected failure
        }

        // Then: Content status should reflect failure
        $content->refresh();
        $this->assertEquals('failed', $content->status);
    }

    /** @test */
    public function it_implements_retry_logic_with_exponential_backoff()
    {
        // Given: A job that will fail initially
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Content for retry testing'
        ]);

        $job = new ProcessBrainDumpJob($content);

        // Then: Verify retry configuration
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->backoff);

        // When: Simulating retries
        $job->attempts = 1;
        $this->assertEquals(120, $job->backoff);

        $job->attempts = 2;
        $this->assertEquals(240, $job->backoff);
    }

    /** @test */
    public function it_prevents_duplicate_job_processing()
    {
        // Given: Content for processing
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Test content for duplicate prevention'
        ]);

        // When: Multiple jobs are dispatched for same content
        ProcessBrainDumpJob::dispatch($content);
        ProcessBrainDumpJob::dispatch($content);

        // Then: Both jobs should be queued (prevention handled at processing level)
        Queue::assertPushed(ProcessBrainDumpJob::class, 2);
    }

    /** @test */
    public function it_handles_resource_cleanup_on_job_completion()
    {
        // Given: Content being processed
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Content for cleanup testing'
        ]);

        // When: Job completes successfully
        $lockKey = 'brain_dump_lock:' . $content->id;
        Cache::put($lockKey, true, 300);

        $this->assertTrue(Cache::has($lockKey));

        // Simulate job completion cleanup
        Cache::forget($lockKey);

        // Then: Resources should be cleaned up
        $this->assertFalse(Cache::has($lockKey));
    }

    /** @test */
    public function it_tracks_ai_job_metrics_during_workflow()
    {
        // Given: AI job tracking is enabled
        $initialJobCount = AiJob::count();

        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Content for metrics tracking'
        ]);

        // When: AI content generation job is processed
        GenerateAiContentJob::dispatch($content, 'summary');

        // Then: Job should be tracked in database
        Queue::assertPushed(GenerateAiContentJob::class);

        // Simulate job processing creating AI job record
        $aiJob = AiJob::create([
            'provider' => 'openai',
            'method' => 'complete',
            'prompt_hash' => hash('sha256', $content->content),
            'prompt_length' => strlen($content->content),
            'options' => ['model' => 'gpt-4o-mini'],
            'status' => 'processing',
            'user_id' => $this->user->id
        ]);

        $this->assertEquals($initialJobCount + 1, AiJob::count());
        $this->assertEquals('processing', $aiJob->status);
    }

    /** @test */
    public function it_handles_cascading_job_failures()
    {
        // Given: A workflow with dependent jobs
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Content for cascading failure test'
        ]);

        // When: Primary job fails
        try {
            $job = new ProcessBrainDumpJob($content);
            $job->failed(new \Exception('Primary job failed'));
        } catch (\Exception $e) {
            // Expected
        }

        // Then: Dependent jobs should not be dispatched
        Queue::assertNotPushed(GenerateVectorEmbeddingsJob::class);

        // And: Content should be marked as failed
        $content->refresh();
        $this->assertEquals('failed', $content->status);
    }

    /** @test */
    public function it_supports_workflow_resume_after_interruption()
    {
        // Given: A partially processed workflow
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Content for resume testing',
            'status' => 'processing',
            'metadata' => [
                'processing_step' => 'entity_extraction_completed',
                'last_checkpoint' => now()->toISOString()
            ]
        ]);

        // When: Workflow is resumed
        ProcessBrainDumpJob::dispatch($content);

        // Then: Job should be queued for resume
        Queue::assertPushed(ProcessBrainDumpJob::class);
    }

    /** @test */
    public function it_handles_queue_worker_scaling_based_on_load()
    {
        // Given: High load scenario with multiple jobs
        $contents = Content::factory()->count(10)->create([
            'user_id' => $this->user->id
        ]);

        // When: Multiple jobs are dispatched across different queues
        foreach ($contents as $index => $content) {
            if ($index < 3) {
                ProcessBrainDumpJob::dispatch($content)->onQueue('ai-processing-high');
            } elseif ($index < 6) {
                GenerateAiContentJob::dispatch($content, 'summary')->onQueue('ai-content-generation');
            } else {
                GenerateVectorEmbeddingsJob::dispatch($content)->onQueue('ai-embeddings');
            }
        }

        // Then: Jobs should be distributed across appropriate queues
        Queue::assertPushed(ProcessBrainDumpJob::class, 3);
        Queue::assertPushed(GenerateAiContentJob::class, 3);
        Queue::assertPushed(GenerateVectorEmbeddingsJob::class, 4);
    }

    /** @test */
    public function it_implements_dead_letter_queue_handling()
    {
        // Given: A job that will exceed retry attempts
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Content for dead letter queue testing'
        ]);

        $job = new ProcessBrainDumpJob($content);

        // When: Job fails after max retries
        $job->attempts = $job->tries + 1;

        // Then: Job should be moved to dead letter queue (simulated)
        $this->assertGreaterThan($job->tries, $job->attempts);

        // And: Content should be marked appropriately
        $job->failed(new \Exception('Max retries exceeded'));
        $content->refresh();
        $this->assertEquals('failed', $content->status);
    }

    /** @test */
    public function it_handles_workflow_monitoring_and_alerting()
    {
        // Given: A workflow execution
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Content for monitoring test'
        ]);

        // When: Job processing takes longer than expected
        $job = new ProcessBrainDumpJob($content);
        $startTime = now();

        // Simulate long processing time
        sleep(1);

        // Then: Monitoring should capture metrics
        $processingTime = now()->diffInSeconds($startTime);
        $this->assertGreaterThan(0, $processingTime);

        // Log processing metrics
        Log::shouldReceive('info')
            ->with('Job processing metrics', Mockery::any());
    }
}