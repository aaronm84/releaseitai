<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\ProcessFeedbackLearningJob;
use App\Models\Feedback;
use App\Models\Input;
use App\Models\Output;
use App\Models\User;
use App\Services\FeedbackService;
use App\Exceptions\AiServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Mockery;

class ProcessFeedbackLearningJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Feedback $feedback;
    protected Input $input;
    protected Output $output;
    protected $mockFeedbackService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->input = Input::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Extract stakeholders from meeting notes'
        ]);

        $this->output = Output::factory()->create([
            'input_id' => $this->input->id,
            'content' => json_encode(['stakeholders' => ['John Doe', 'Jane Smith']]),
            'confidence_score' => 0.85
        ]);

        $this->feedback = Feedback::factory()->create([
            'input_id' => $this->input->id,
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
            'feedback_type' => 'correction',
            'feedback_data' => json_encode([
                'corrected_stakeholders' => ['John Doe', 'Jane Smith', 'Mike Johnson'],
                'missing_entities' => ['Mike Johnson']
            ]),
            'status' => 'pending'
        ]);

        $this->mockFeedbackService = Mockery::mock(FeedbackService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_be_instantiated_with_feedback()
    {
        $job = new ProcessFeedbackLearningJob($this->feedback);

        $this->assertInstanceOf(ProcessFeedbackLearningJob::class, $job);
        $this->assertEquals($this->feedback->id, $job->feedback->id);
    }

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new ProcessFeedbackLearningJob($this->feedback);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_has_correct_queue_configuration()
    {
        $job = new ProcessFeedbackLearningJob($this->feedback);

        $this->assertEquals('ai-learning', $job->queue);
        $this->assertEquals(180, $job->timeout); // 3 minutes
        $this->assertEquals(5, $job->tries); // More retries for learning
        $this->assertEquals([30, 60, 120, 240, 480], $job->backoff); // Progressive backoff
    }

    /** @test */
    public function it_processes_positive_feedback_successfully()
    {
        $positiveFeedback = Feedback::factory()->create([
            'input_id' => $this->input->id,
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
            'feedback_type' => 'positive',
            'feedback_data' => json_encode(['rating' => 5, 'comment' => 'Perfect extraction']),
            'status' => 'pending'
        ]);

        $this->mockFeedbackService
            ->shouldReceive('processPositiveFeedback')
            ->once()
            ->with($positiveFeedback)
            ->andReturn([
                'confidence_boost' => 0.05,
                'pattern_reinforced' => true,
                'model_updated' => true
            ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Starting feedback learning processing', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Feedback learning processing completed', Mockery::type('array'));

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($positiveFeedback);
        $job->handle($this->mockFeedbackService);

        $positiveFeedback->refresh();
        $this->assertEquals('processed', $positiveFeedback->status);
        $this->assertNotNull($positiveFeedback->processed_at);
    }

    /** @test */
    public function it_processes_correction_feedback_successfully()
    {
        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->with($this->feedback)
            ->andReturn([
                'patterns_updated' => ['stakeholder_detection'],
                'embeddings_retrained' => true,
                'confidence_adjustments' => ['entity_extraction' => -0.1],
                'new_training_samples' => 3
            ]);

        Log::shouldReceive('info')->twice();

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $job->handle($this->mockFeedbackService);

        $this->feedback->refresh();
        $this->assertEquals('processed', $this->feedback->status);
        $this->assertArrayHasKey('learning_metrics', $this->feedback->metadata);
    }

    /** @test */
    public function it_processes_negative_feedback_successfully()
    {
        $negativeFeedback = Feedback::factory()->create([
            'input_id' => $this->input->id,
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
            'feedback_type' => 'negative',
            'feedback_data' => json_encode([
                'rating' => 1,
                'issues' => ['missed_entities', 'false_positives'],
                'comment' => 'Many stakeholders were missed'
            ]),
            'status' => 'pending'
        ]);

        $this->mockFeedbackService
            ->shouldReceive('processNegativeFeedback')
            ->once()
            ->with($negativeFeedback)
            ->andReturn([
                'confidence_penalty' => -0.15,
                'patterns_flagged' => ['entity_extraction_context'],
                'retraining_triggered' => true
            ]);

        Log::shouldReceive('info')->twice();

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($negativeFeedback);
        $job->handle($this->mockFeedbackService);

        $negativeFeedback->refresh();
        $this->assertEquals('processed', $negativeFeedback->status);
    }

    /** @test */
    public function it_handles_batch_feedback_processing()
    {
        $batchFeedback = collect([
            $this->feedback,
            Feedback::factory()->create([
                'input_id' => $this->input->id,
                'user_id' => $this->user->id,
                'feedback_type' => 'positive'
            ])
        ]);

        $this->mockFeedbackService
            ->shouldReceive('processBatchFeedback')
            ->once()
            ->with(Mockery::type('Illuminate\Support\Collection'))
            ->andReturn([
                'processed_count' => 2,
                'patterns_updated' => ['stakeholder_detection', 'context_analysis'],
                'batch_learning_applied' => true
            ]);

        Log::shouldReceive('info')->twice();

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback, $batchFeedback);
        $job->handle($this->mockFeedbackService);
    }

    /** @test */
    public function it_handles_vector_embedding_updates()
    {
        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->andReturn([
                'embeddings_updated' => true,
                'vector_space_adjusted' => true,
                'similarity_threshold_updated' => 0.82
            ]);

        $this->mockFeedbackService
            ->shouldReceive('updateVectorEmbeddings')
            ->once()
            ->with($this->feedback)
            ->andReturn(true);

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('info')
            ->once()
            ->with('Vector embeddings updated based on feedback', Mockery::type('array'));

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $job->handle($this->mockFeedbackService);
    }

    /** @test */
    public function it_handles_ai_service_failures_gracefully()
    {
        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->with($this->feedback)
            ->andThrow(new AiServiceException('Vector database connection failed'));

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->once()
            ->with('Feedback learning processing failed', Mockery::type('array'));

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);

        $this->expectException(AiServiceException::class);
        $job->handle($this->mockFeedbackService);

        $this->feedback->refresh();
        $this->assertEquals('failed', $this->feedback->status);
    }

    /** @test */
    public function it_implements_exponential_backoff_for_retries()
    {
        $job = new ProcessFeedbackLearningJob($this->feedback);

        $expectedBackoff = [30, 60, 120, 240, 480];
        $this->assertEquals($expectedBackoff, $job->backoff);
    }

    /** @test */
    public function it_prevents_duplicate_feedback_processing()
    {
        $lockKey = 'feedback_learning_lock:' . $this->feedback->id;

        Cache::shouldReceive('add')
            ->once()
            ->with($lockKey, true, 300)
            ->andReturn(false); // Lock already exists

        Log::shouldReceive('warning')
            ->once()
            ->with('Feedback learning already in progress', Mockery::type('array'));

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $result = $job->handle($this->mockFeedbackService);

        $this->assertNull($result);
    }

    /** @test */
    public function it_tracks_learning_metrics()
    {
        $learningResult = [
            'confidence_adjustment' => 0.05,
            'patterns_updated' => ['entity_extraction'],
            'training_samples_added' => 5,
            'model_accuracy_improvement' => 0.02
        ];

        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->andReturn($learningResult);

        Cache::shouldReceive('add')->once()->andReturn(true);
        Cache::shouldReceive('forget')->once();

        Log::shouldReceive('info')->twice();

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $job->handle($this->mockFeedbackService);

        $this->feedback->refresh();
        $metadata = $this->feedback->metadata;
        $this->assertEquals($learningResult, $metadata['learning_metrics']);
        $this->assertArrayHasKey('processing_duration_ms', $metadata);
    }

    /** @test */
    public function it_handles_confidence_score_updates()
    {
        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->andReturn([
                'confidence_adjustments' => [
                    'original_output_id' => $this->output->id,
                    'new_confidence' => 0.75,
                    'adjustment_reason' => 'user_correction'
                ]
            ]);

        $this->mockFeedbackService
            ->shouldReceive('updateOutputConfidence')
            ->once()
            ->with($this->output->id, 0.75)
            ->andReturn(true);

        Log::shouldReceive('info')->twice();

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $job->handle($this->mockFeedbackService);

        $this->output->refresh();
        $this->assertEquals(0.75, $this->output->confidence_score);
    }

    /** @test */
    public function it_triggers_model_retraining_when_threshold_reached()
    {
        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->andReturn([
                'retraining_triggered' => true,
                'feedback_threshold_reached' => true,
                'accumulated_feedback_count' => 100
            ]);

        $this->mockFeedbackService
            ->shouldReceive('triggerModelRetraining')
            ->once()
            ->with('entity_extraction')
            ->andReturn(['job_id' => 'retrain_123', 'status' => 'queued']);

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('info')
            ->once()
            ->with('Model retraining triggered based on feedback threshold', Mockery::type('array'));

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $job->handle($this->mockFeedbackService);
    }

    /** @test */
    public function it_handles_feedback_aggregation()
    {
        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->andReturn([
                'aggregation_applied' => true,
                'similar_feedback_count' => 5,
                'pattern_strength_increased' => true
            ]);

        $this->mockFeedbackService
            ->shouldReceive('aggregateSimilarFeedback')
            ->once()
            ->with($this->feedback)
            ->andReturn([
                'aggregated_samples' => 5,
                'pattern_confidence' => 0.92
            ]);

        Log::shouldReceive('info')->twice();

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $job->handle($this->mockFeedbackService);
    }

    /** @test */
    public function it_cleans_up_resources_on_completion()
    {
        $lockKey = 'feedback_learning_lock:' . $this->feedback->id;

        Cache::shouldReceive('add')->once()->andReturn(true);
        Cache::shouldReceive('forget')->once()->with($lockKey);

        $this->mockFeedbackService
            ->shouldReceive('processCorrectionFeedback')
            ->once()
            ->andReturn(['success' => true]);

        Log::shouldReceive('info')->twice();

        $this->app->instance(FeedbackService::class, $this->mockFeedbackService);

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $job->handle($this->mockFeedbackService);
    }

    /** @test */
    public function it_handles_failed_method_correctly()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('ProcessFeedbackLearningJob failed', Mockery::type('array'));

        $job = new ProcessFeedbackLearningJob($this->feedback);
        $exception = new \Exception('Learning process failed');

        $job->failed($exception);

        $this->feedback->refresh();
        $this->assertEquals('failed', $this->feedback->status);
        $this->assertStringContains('Learning process failed', $this->feedback->metadata['error_message']);
    }

    /** @test */
    public function it_validates_feedback_data_before_processing()
    {
        $invalidFeedback = Feedback::factory()->create([
            'feedback_data' => 'invalid json',
            'status' => 'pending'
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Invalid feedback data format', Mockery::type('array'));

        $job = new ProcessFeedbackLearningJob($invalidFeedback);

        $this->expectException(\InvalidArgumentException::class);
        $job->handle($this->mockFeedbackService);
    }
}