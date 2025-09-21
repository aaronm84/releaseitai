<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Content;
use App\Models\ActionItem;
use App\Services\AiService;
use App\Services\BrainDumpProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Mockery;

class BrainDumpWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private string $endpoint = '/api/brain-dump/process';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * @group integration
     * @group brain-dump-workflow
     */
    public function completeBrainDumpWorkflow_ProcessesAndStoresCorrectly(): void
    {
        // Given: A comprehensive brain dump with various content types
        $brainDumpContent = "
            Product Planning Meeting - January 15, 2024

            Attendees: Sarah (PM), Mike (Dev Lead), Lisa (Designer)

            Key Discussions:
            - User authentication system needs to be completed by January 30th (HIGH PRIORITY)
            - Design review session scheduled for January 22nd
            - Decision made: We're adopting TypeScript for all new components
            - Bug fix for login redirect issue assigned to Mike
            - All-hands meeting planned for February 1st, 2024

            Action Items:
            1. Complete OAuth integration (Sarah) - Due: Jan 25
            2. Update design system documentation (Lisa) - Due: Jan 28
            3. Review performance metrics (Mike) - Due: Jan 20

            Decisions Made:
            - Moving from JavaScript to TypeScript (High Impact)
            - Postponing mobile app release to Q2 (Medium Impact)
            - Implementing automated testing pipeline (High Impact)

            Stakeholder Feedback:
            - CEO wants quarterly review presentation
            - Marketing team needs feature specifications
        ";

        $this->mockAiServiceForWorkflow($brainDumpContent);

        // When: Processing the complete brain dump
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, [
                'content' => $brainDumpContent,
                'save_to_content' => true,
                'extract_entities' => true
            ]);

        // Then: Returns comprehensive structured data
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tasks' => [
                        '*' => ['title', 'priority', 'assignee', 'due_date']
                    ],
                    'meetings' => [
                        '*' => ['title', 'date', 'attendees']
                    ],
                    'decisions' => [
                        '*' => ['title', 'impact', 'date']
                    ],
                    'stakeholders' => [
                        '*' => ['name', 'role', 'context']
                    ]
                ],
                'content_id',
                'processing_time',
                'timestamp'
            ]);

        $responseData = $response->json('data');

        // Verify tasks extraction
        $this->assertCount(3, $responseData['tasks']);
        $this->assertEquals('Complete OAuth integration', $responseData['tasks'][0]['title']);
        $this->assertEquals('high', $responseData['tasks'][0]['priority']);
        $this->assertEquals('Sarah', $responseData['tasks'][0]['assignee']);

        // Verify meetings extraction
        $this->assertCount(2, $responseData['meetings']);
        $meetingTitles = array_column($responseData['meetings'], 'title');
        $this->assertContains('Design review session', $meetingTitles);
        $this->assertContains('All-hands meeting', $meetingTitles);

        // Verify decisions extraction
        $this->assertCount(3, $responseData['decisions']);
        $decisionTitles = array_column($responseData['decisions'], 'title');
        $this->assertContains('Moving from JavaScript to TypeScript', $decisionTitles);

        // Verify content was saved
        $this->assertDatabaseHas('contents', [
            'user_id' => $this->user->id,
            'type' => 'brain_dump',
            'status' => 'processed'
        ]);

        $contentId = $response->json('content_id');
        $content = Content::find($contentId);
        $this->assertNotNull($content);
        $this->assertEquals('brain_dump', $content->type);
    }

    /**
     * @test
     * @group integration
     * @group brain-dump-workflow
     */
    public function brainDumpWorkflow_CreatesActionItemsInDatabase(): void
    {
        // Given: Brain dump with specific action items
        $content = "
            Sprint planning outcomes:
            - Complete user registration API (John) - Priority: High - Due: 2024-01-25
            - Update database schema (Alice) - Priority: Medium - Due: 2024-01-30
            - Write integration tests (Bob) - Priority: Low - Due: 2024-02-05
        ";

        $this->mockAiServiceForWorkflow($content);

        // When: Processing and saving to database
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, [
                'content' => $content,
                'save_to_content' => true,
                'create_action_items' => true
            ]);

        // Then: Action items are created in database
        $response->assertStatus(200);

        $this->assertDatabaseHas('action_items', [
            'action_text' => 'Complete user registration API',
            'priority' => 'high',
            'due_date' => '2024-01-25'
        ]);

        $this->assertDatabaseHas('action_items', [
            'action_text' => 'Update database schema',
            'priority' => 'medium',
            'due_date' => '2024-01-30'
        ]);

        $this->assertDatabaseHas('action_items', [
            'action_text' => 'Write integration tests',
            'priority' => 'low',
            'due_date' => '2024-02-05'
        ]);
    }

    /**
     * @test
     * @group integration
     * @group brain-dump-workflow
     */
    public function brainDumpWorkflow_HandlesLargeContentEfficiently(): void
    {
        // Given: Large brain dump content (approaching limit)
        $baseContent = "Comprehensive project review meeting with detailed notes: ";
        $largeContent = $baseContent . str_repeat("Important point about the project development and stakeholder feedback. ", 120);

        $this->mockAiServiceForWorkflow($largeContent);

        // When: Processing large content
        $startTime = microtime(true);
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, [
                'content' => $largeContent,
                'optimize_for_size' => true
            ]);
        $endTime = microtime(true);

        // Then: Processes efficiently within reasonable time
        $response->assertStatus(200);

        $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $this->assertLessThan(5000, $processingTime, 'Processing should complete within 5 seconds');

        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('processing_time', $responseData);
    }

    /**
     * @test
     * @group integration
     * @group brain-dump-workflow
     */
    public function brainDumpWorkflow_WithMultipleUsersParallel_HandlesCorrectly(): void
    {
        // Given: Multiple users processing brain dumps simultaneously
        $user1 = User::factory()->create(['name' => 'User One']);
        $user2 = User::factory()->create(['name' => 'User Two']);

        $content1 = "User 1 brain dump: Complete task A by tomorrow (high priority)";
        $content2 = "User 2 brain dump: Schedule meeting B next week (medium priority)";

        $this->mockAiServiceForWorkflow($content1);
        $this->mockAiServiceForWorkflow($content2);

        // When: Both users process simultaneously
        $response1 = $this->actingAs($user1)
            ->postJson($this->endpoint, ['content' => $content1, 'save_to_content' => true]);

        $response2 = $this->actingAs($user2)
            ->postJson($this->endpoint, ['content' => $content2, 'save_to_content' => true]);

        // Then: Both process successfully with isolated data
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Verify user isolation
        $content1Id = $response1->json('content_id');
        $content2Id = $response2->json('content_id');

        $savedContent1 = Content::find($content1Id);
        $savedContent2 = Content::find($content2Id);

        $this->assertEquals($user1->id, $savedContent1->user_id);
        $this->assertEquals($user2->id, $savedContent2->user_id);
        $this->assertNotEquals($content1Id, $content2Id);
    }

    /**
     * @test
     * @group integration
     * @group brain-dump-workflow
     */
    public function brainDumpWorkflow_WithReprocessingRequest_UpdatesExistingContent(): void
    {
        // Given: Initial brain dump and subsequent reprocessing
        $initialContent = "Initial meeting notes: Task A needs completion";
        $updatedContent = "Updated meeting notes: Task A completed, Task B needs attention (high priority)";

        $this->mockAiServiceForWorkflow($initialContent);

        // Create initial content
        $initialResponse = $this->actingAs($this->user)
            ->postJson($this->endpoint, [
                'content' => $initialContent,
                'save_to_content' => true
            ]);

        $contentId = $initialResponse->json('content_id');

        $this->mockAiServiceForWorkflow($updatedContent);

        // When: Reprocessing with updated content
        $response = $this->actingAs($this->user)
            ->putJson("/api/brain-dump/reprocess/{$contentId}", [
                'content' => $updatedContent
            ]);

        // Then: Updates existing content instead of creating new
        $response->assertStatus(200);

        $updatedContentRecord = Content::find($contentId);
        $this->assertStringContainsString('Updated meeting notes', $updatedContentRecord->content);

        // Verify only one content record exists for this user
        $userContentCount = Content::where('user_id', $this->user->id)
            ->where('type', 'brain_dump')
            ->count();
        $this->assertEquals(1, $userContentCount);
    }

    /**
     * @test
     * @group integration
     * @group brain-dump-workflow
     */
    public function brainDumpWorkflow_WithFailedAiService_CleansUpGracefully(): void
    {
        // Given: Content that will cause AI service to fail
        $content = "Content that will trigger AI failure";

        // Mock AI service to fail
        $aiServiceMock = Mockery::mock(AiService::class);
        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andThrow(new \App\Exceptions\AiServiceException('Service temporarily unavailable'));

        $this->app->instance(AiService::class, $aiServiceMock);

        // When: Processing fails
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, [
                'content' => $content,
                'save_to_content' => true
            ]);

        // Then: Returns error and doesn't create partial content
        $response->assertStatus(503);

        // Verify no content was created
        $this->assertDatabaseMissing('contents', [
            'user_id' => $this->user->id,
            'type' => 'brain_dump',
            'status' => 'processed'
        ]);

        // Verify no action items were created
        $this->assertEquals(0, ActionItem::where('user_id', $this->user->id)->count());
    }

    /**
     * @test
     * @group integration
     * @group brain-dump-workflow
     */
    public function brainDumpWorkflow_WithPartialProcessingFailure_ReturnsPartialResults(): void
    {
        // Given: Content where some processing succeeds and some fails
        $content = "Meeting notes with tasks and decisions";

        $aiServiceMock = Mockery::mock(AiService::class);

        // Action items succeed
        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andReturn($this->createMockAiResponse(json_encode([
                'action_items' => [
                    ['text' => 'Complete task', 'priority' => 'high']
                ]
            ])));

        // Entity analysis fails
        $aiServiceMock->shouldReceive('analyzeContentEntities')
            ->once()
            ->andThrow(new \App\Exceptions\AiServiceException('Entity analysis failed'));

        $this->app->instance(AiService::class, $aiServiceMock);

        // When: Processing with partial failure
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, [
                'content' => $content,
                'allow_partial_results' => true
            ]);

        // Then: Returns partial results with warnings
        $response->assertStatus(206) // Partial Content
            ->assertJson([
                'success' => true,
                'data' => [
                    'tasks' => [
                        ['title' => 'Complete task', 'priority' => 'high']
                    ],
                    'meetings' => [],
                    'decisions' => []
                ],
                'warnings' => [
                    'Entity analysis failed - some data may be incomplete'
                ]
            ]);
    }

    // Helper Methods

    private function mockAiServiceForWorkflow(string $content): void
    {
        $aiServiceMock = Mockery::mock(AiService::class);

        // Mock action items extraction
        $aiServiceMock->shouldReceive('extractActionItems')
            ->with($content)
            ->andReturn($this->createMockAiResponse(json_encode([
                'action_items' => [
                    [
                        'text' => 'Complete OAuth integration',
                        'priority' => 'high',
                        'assignee' => 'Sarah',
                        'due_date' => '2024-01-25'
                    ],
                    [
                        'text' => 'Update design system documentation',
                        'priority' => 'medium',
                        'assignee' => 'Lisa',
                        'due_date' => '2024-01-28'
                    ],
                    [
                        'text' => 'Review performance metrics',
                        'priority' => 'medium',
                        'assignee' => 'Mike',
                        'due_date' => '2024-01-20'
                    ]
                ]
            ])));

        // Mock entity analysis
        $aiServiceMock->shouldReceive('analyzeContentEntities')
            ->with($content)
            ->andReturn([
                'stakeholders' => [
                    ['name' => 'Sarah', 'confidence' => 0.95, 'context' => 'PM'],
                    ['name' => 'Mike', 'confidence' => 0.90, 'context' => 'Dev Lead'],
                    ['name' => 'Lisa', 'confidence' => 0.88, 'context' => 'Designer']
                ],
                'workstreams' => [
                    ['name' => 'Authentication System', 'confidence' => 0.85]
                ],
                'releases' => [],
                'action_items' => [],
                'summary' => 'Product planning meeting discussing authentication system and team decisions'
            ]);

        $this->app->instance(AiService::class, $aiServiceMock);
    }

    private function createMockAiResponse(string $content)
    {
        $mock = Mockery::mock();
        $mock->shouldReceive('getContent')->andReturn($content);
        $mock->shouldReceive('getTokensUsed')->andReturn(150);
        $mock->shouldReceive('getCost')->andReturn(0.02);
        return $mock;
    }
}