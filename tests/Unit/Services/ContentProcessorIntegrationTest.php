<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ContentProcessor;
use App\Services\AiService;
use App\Models\Content;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ContentActionItem;
use App\Models\User;
use App\Services\AiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;

/**
 * Integration tests for ContentProcessor service interface and system interactions
 * These tests validate how ContentProcessor integrates with the broader application ecosystem
 */
class ContentProcessorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ContentProcessor $contentProcessor;
    private $mockAiService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAiService = Mockery::mock(AiService::class);
        $this->contentProcessor = new ContentProcessor($this->mockAiService);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test Case: Service Interface - Required Public Methods
     *
     * Given: ContentProcessor service class
     * When: I examine its public interface
     * Then: It should expose all required methods for content processing workflow
     */
    public function test_exposes_required_public_interface()
    {
        $requiredMethods = [
            'process',                      // Main processing method
            'processBatch',                 // Batch processing
            'matchEntities',                // Entity matching
            'generateConfirmationTasks',    // Confirmation workflow
            'generateBatchConfirmationTasks', // Batch confirmation workflow
            'executeConfirmationTask',      // Execute user confirmations
            'getSupportedContentTypes',     // Get supported content types
            'validateContent',              // Content validation
            'getProcessingStats'            // Processing statistics
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($this->contentProcessor, $method),
                "ContentProcessor must implement public method: {$method}"
            );
        }
    }

    /**
     * Test Case: Service Integration - Content Type Support
     *
     * Given: ContentProcessor service
     * When: I query supported content types
     * Then: It should return all content types the service can process
     */
    public function test_returns_supported_content_types()
    {
        $supportedTypes = $this->contentProcessor->getSupportedContentTypes();

        $expectedTypes = [
            'brain_dump',
            'email',
            'slack',
            'document',
            'meeting_notes',
            'manual'
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $supportedTypes);
        }

        // Each type should have metadata about processing capabilities
        foreach ($supportedTypes as $type) {
            $typeInfo = $this->contentProcessor->getContentTypeInfo($type);
            $this->assertArrayHasKey('name', $typeInfo);
            $this->assertArrayHasKey('description', $typeInfo);
            $this->assertArrayHasKey('supported_entities', $typeInfo);
            $this->assertArrayHasKey('metadata_fields', $typeInfo);
        }
    }

    /**
     * Test Case: Service Integration - Content Validation Rules
     *
     * Given: Different content inputs and types
     * When: ContentProcessor validates content
     * Then: It should apply type-specific validation rules
     */
    public function test_applies_content_type_specific_validation()
    {
        // Test brain_dump validation
        $this->expectException(\InvalidArgumentException::class);
        $this->contentProcessor->validateContent("", 'brain_dump');

        // Test email validation
        $validEmailContent = "From: test@example.com\nSubject: Test\n\nContent here";
        $this->assertTrue($this->contentProcessor->validateContent($validEmailContent, 'email'));

        // Test document validation with metadata
        $documentMetadata = ['file_name' => 'test.pdf', 'file_size' => 1024];
        $this->assertTrue($this->contentProcessor->validateContent("Document content", 'document', $documentMetadata));

        // Test slack validation
        $slackContent = "@channel urgent issue #critical";
        $this->assertTrue($this->contentProcessor->validateContent($slackContent, 'slack'));
    }

    /**
     * Test Case: Service Integration - Event System Integration
     *
     * Given: ContentProcessor processes content
     * When: Processing completes successfully
     * Then: It should dispatch appropriate events for other system components
     */
    public function test_dispatches_events_during_processing()
    {
        Event::fake();

        $content = "Meeting with Sarah about Q1 release planning.";

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Sarah', 'confidence' => 0.95, 'context' => 'meeting participant']
            ],
            'releases' => [
                ['version' => 'Q1 release', 'confidence' => 0.90, 'context' => 'planning topic']
            ],
            'action_items' => [],
            'summary' => 'Q1 release planning meeting'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'brain_dump');

        // Should dispatch content processing events
        Event::assertDispatched('content.processing.started');
        Event::assertDispatched('content.processing.completed');
        Event::assertDispatched('entities.extracted');

        // Should dispatch specific entity events if entities are found
        if (!empty($result['extracted_entities']['stakeholders'])) {
            Event::assertDispatched('stakeholder.mentioned');
        }
        if (!empty($result['extracted_entities']['releases'])) {
            Event::assertDispatched('release.mentioned');
        }
    }

    /**
     * Test Case: Service Integration - Queue System Integration
     *
     * Given: Large content or batch processing
     * When: ContentProcessor processes content
     * Then: It should queue appropriate background jobs for heavy operations
     */
    public function test_queues_background_jobs_for_heavy_operations()
    {
        Queue::fake();

        $largeContent = str_repeat("This is content that requires background processing. ", 100);

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn([
                'stakeholders' => [],
                'workstreams' => [],
                'releases' => [],
                'action_items' => [],
                'summary' => 'Large content processed'
            ]);

        $this->contentProcessor->process($largeContent, 'document', ['background_processing' => true]);

        // Should queue background jobs for heavy operations
        Queue::assertPushed('App\Jobs\ProcessEntityRelationships');
        Queue::assertPushed('App\Jobs\GenerateContentInsights');
        Queue::assertPushed('App\Jobs\UpdateEntityRelevanceScores');
    }

    /**
     * Test Case: Service Integration - Database Transaction Management
     *
     * Given: ContentProcessor processes content with multiple entity operations
     * When: An error occurs during processing
     * Then: It should rollback all database changes to maintain data consistency
     */
    public function test_maintains_database_consistency_on_errors()
    {
        $content = "Meeting with new stakeholder John about critical workstream.";

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn([
                'stakeholders' => [
                    ['name' => 'John', 'confidence' => 0.95, 'context' => 'new stakeholder']
                ],
                'workstreams' => [
                    ['name' => 'critical workstream', 'confidence' => 0.90, 'context' => 'discussed']
                ],
                'action_items' => [],
                'summary' => 'New stakeholder discussion'
            ]);

        // Force an error during entity relationship creation
        $originalCount = Content::count();

        try {
            // Mock a database error after content creation but before relationships
            $this->contentProcessor->process($content, 'brain_dump', ['simulate_error' => true]);
        } catch (\Exception $e) {
            // Verify rollback occurred
            $this->assertEquals($originalCount, Content::count());
            $this->assertEquals(0, DB::table('content_stakeholders')->count());
            $this->assertEquals(0, DB::table('content_workstreams')->count());
        }
    }

    /**
     * Test Case: Service Integration - Caching Strategy
     *
     * Given: ContentProcessor with caching enabled
     * When: Similar content is processed multiple times
     * Then: It should leverage caching to improve performance
     */
    public function test_leverages_caching_for_performance()
    {
        $content = "Regular team standup with the same stakeholders.";

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Team Lead', 'confidence' => 0.95, 'context' => 'standup participant']
            ],
            'workstreams' => [],
            'releases' => [],
            'action_items' => [],
            'summary' => 'Team standup'
        ];

        // First processing - should call AI service
        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result1 = $this->contentProcessor->process($content, 'brain_dump', ['use_cache' => true]);

        // Second processing of similar content - should use cache
        $similarContent = "Team standup with usual stakeholders including Team Lead.";
        $result2 = $this->contentProcessor->process($similarContent, 'brain_dump', ['use_cache' => true]);

        // Verify caching behavior through processing stats
        $stats = $this->contentProcessor->getProcessingStats();
        $this->assertGreaterThan(0, $stats['cache_hits']);
        $this->assertEquals(2, $stats['total_requests']);
        $this->assertEquals(1, $stats['ai_service_calls']); // Only one actual AI call
    }

    /**
     * Test Case: Service Integration - Webhook Integration
     *
     * Given: ContentProcessor processes content from external sources
     * When: Content contains webhook-triggering entities
     * Then: It should trigger configured webhooks for external system integration
     */
    public function test_triggers_webhooks_for_external_integration()
    {
        Queue::fake();

        $content = "CRITICAL: Production release v2.1.0 failed deployment.";

        $mockAiResponse = [
            'stakeholders' => [],
            'workstreams' => [],
            'releases' => [
                [
                    'version' => 'v2.1.0',
                    'confidence' => 0.95,
                    'context' => 'failed deployment',
                    'severity' => 'critical'
                ]
            ],
            'action_items' => [
                [
                    'text' => 'Investigate deployment failure',
                    'priority' => 'critical',
                    'confidence' => 0.90
                ]
            ],
            'summary' => 'Critical production deployment failure'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $this->contentProcessor->process($content, 'email', ['source' => 'monitoring_system']);

        // Should queue webhook notifications for critical issues
        Queue::assertPushed('App\Jobs\TriggerWebhook', function ($job) {
            return $job->webhookType === 'release_failure' &&
                   $job->severity === 'critical';
        });
    }

    /**
     * Test Case: Service Integration - Audit Trail and Logging
     *
     * Given: ContentProcessor processes content
     * When: Processing involves entity operations
     * Then: It should create comprehensive audit trails for compliance and debugging
     */
    public function test_creates_comprehensive_audit_trails()
    {
        $content = "Board meeting: Approved Q2 budget for mobile platform initiative.";

        $mockAiResponse = [
            'stakeholders' => [],
            'workstreams' => [
                ['name' => 'mobile platform initiative', 'confidence' => 0.90, 'context' => 'budget approved']
            ],
            'releases' => [],
            'action_items' => [],
            'summary' => 'Q2 budget approval for mobile platform'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'brain_dump');

        // Verify audit trail creation
        $this->assertDatabaseHas('content_processing_logs', [
            'content_id' => $result['content_record']->id,
            'user_id' => $this->user->id,
            'action' => 'content_processed',
            'entities_extracted' => 1, // mobile platform workstream
            'confidence_scores' => json_encode(['workstreams' => [0.90]]),
            'processing_duration' => 'NOT NULL'
        ]);
    }

    /**
     * Test Case: Service Integration - API Response Format
     *
     * Given: ContentProcessor processes content
     * When: Results are returned
     * Then: They should follow a consistent API response format suitable for frontend consumption
     */
    public function test_returns_consistent_api_response_format()
    {
        $content = "Quick sync with development team about API endpoints.";

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Development Team', 'confidence' => 0.90, 'context' => 'sync participants']
            ],
            'workstreams' => [
                ['name' => 'API development', 'confidence' => 0.85, 'context' => 'endpoints discussion']
            ],
            'releases' => [],
            'action_items' => [],
            'summary' => 'Development team sync about API endpoints'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'brain_dump');

        // Verify response structure
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $this->assertArrayHasKey('data', $result);
        $data = $result['data'];

        $this->assertArrayHasKey('content_record', $data);
        $this->assertArrayHasKey('extracted_entities', $data);
        $this->assertArrayHasKey('entity_matches', $data);
        $this->assertArrayHasKey('confirmation_tasks', $data);
        $this->assertArrayHasKey('processing_metadata', $data);

        // Verify processing metadata
        $metadata = $data['processing_metadata'];
        $this->assertArrayHasKey('processing_time_ms', $metadata);
        $this->assertArrayHasKey('ai_confidence_avg', $metadata);
        $this->assertArrayHasKey('entities_count', $metadata);
        $this->assertArrayHasKey('confirmation_tasks_count', $metadata);
    }

    /**
     * Test Case: Service Integration - Performance Monitoring
     *
     * Given: ContentProcessor with performance monitoring enabled
     * When: Content is processed
     * Then: It should collect and report performance metrics
     */
    public function test_collects_performance_metrics()
    {
        $content = "Performance test content for monitoring.";

        $mockAiResponse = [
            'stakeholders' => [],
            'workstreams' => [],
            'releases' => [],
            'action_items' => [],
            'summary' => 'Performance test'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $startTime = microtime(true);
        $result = $this->contentProcessor->process($content, 'brain_dump');
        $endTime = microtime(true);

        $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Verify performance metrics are collected
        $this->assertArrayHasKey('processing_metadata', $result['data']);
        $metadata = $result['data']['processing_metadata'];

        $this->assertArrayHasKey('processing_time_ms', $metadata);
        $this->assertLessThan($processingTime + 100, $metadata['processing_time_ms']); // Allow small variance
        $this->assertArrayHasKey('memory_usage_mb', $metadata);
        $this->assertArrayHasKey('ai_service_time_ms', $metadata);
        $this->assertArrayHasKey('database_operations_count', $metadata);
    }

    /**
     * Test Case: Service Integration - Error Recovery and Graceful Degradation
     *
     * Given: ContentProcessor encounters various system failures
     * When: Processing continues despite errors
     * Then: It should gracefully degrade functionality while preserving core operations
     */
    public function test_gracefully_degrades_on_system_failures()
    {
        $content = "Important meeting notes that must be saved.";

        // Test AI service failure
        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andThrow(new \Exception('AI service temporarily unavailable'));

        $result = $this->contentProcessor->process($content, 'brain_dump', ['graceful_degradation' => true]);

        // Should still save content even if AI processing fails
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('content_record', $result['data']);
        $this->assertEquals('partially_processed', $result['data']['content_record']->status);

        // Should indicate degraded functionality
        $this->assertArrayHasKey('warnings', $result);
        $this->assertStringContainsString('AI service unavailable', $result['warnings'][0]['message']);

        // Should provide fallback entity extraction
        $this->assertArrayHasKey('extracted_entities', $result['data']);
        $this->assertArrayHasKey('fallback_extraction_used', $result['data']['processing_metadata']);
        $this->assertTrue($result['data']['processing_metadata']['fallback_extraction_used']);
    }

    /**
     * Test Case: Service Integration - Multi-tenant Data Isolation
     *
     * Given: Multiple users using ContentProcessor
     * When: Content is processed
     * Then: It should maintain strict data isolation between users/tenants
     */
    public function test_maintains_multi_tenant_data_isolation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create stakeholders for each user
        $user1Stakeholder = Stakeholder::factory()->create([
            'name' => 'Shared Name',
            'user_id' => $user1->id
        ]);

        $user2Stakeholder = Stakeholder::factory()->create([
            'name' => 'Shared Name',
            'user_id' => $user2->id
        ]);

        $content = "Meeting with Shared Name about project status.";

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Shared Name', 'confidence' => 0.95, 'context' => 'meeting participant']
            ],
            'workstreams' => [],
            'releases' => [],
            'action_items' => [],
            'summary' => 'Project status meeting'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->twice()
            ->andReturn($mockAiResponse);

        // Process as user1
        $this->actingAs($user1);
        $result1 = $this->contentProcessor->process($content, 'brain_dump');

        // Process as user2
        $this->actingAs($user2);
        $result2 = $this->contentProcessor->process($content, 'brain_dump');

        // Verify each user only sees their own stakeholder
        $this->assertEquals($user1Stakeholder->id,
            $result1['data']['entity_matches']['stakeholders']['matches'][0]['existing_entity']->id);
        $this->assertEquals($user2Stakeholder->id,
            $result2['data']['entity_matches']['stakeholders']['matches'][0]['existing_entity']->id);

        // Verify content records are isolated
        $this->assertEquals($user1->id, $result1['data']['content_record']->user_id);
        $this->assertEquals($user2->id, $result2['data']['content_record']->user_id);
    }
}