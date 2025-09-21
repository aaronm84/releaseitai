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
use Illuminate\Support\Facades\DB;
use Mockery;

class ContentProcessorTest extends TestCase
{
    use RefreshDatabase;

    private ContentProcessor $contentProcessor;
    private AiService $aiService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiService = app(AiService::class);
        $this->contentProcessor = new ContentProcessor($this->aiService);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test Case: Content Type Processing - Brain Dump
     *
     * Given: A brain dump content string
     * When: The ContentProcessor processes the content
     * Then: It should extract entities specific to brain dump context
     */
    public function test_processes_brain_dump_content_type()
    {
        $content = "Had a meeting with Sarah from marketing about Q1 release. Need to update the user auth workstream. Follow up with John by Friday.";

        $result = $this->contentProcessor->process($content, 'brain_dump');

        // Test the structure of the response
        $this->assertArrayHasKey('content_id', $result);
        $this->assertArrayHasKey('extracted_entities', $result);
        $this->assertArrayHasKey('match_results', $result);
        $this->assertArrayHasKey('confirmation_tasks', $result);
        $this->assertArrayHasKey('stats', $result);

        // Test that content was stored properly
        $contentRecord = Content::find($result['content_id']);
        $this->assertNotNull($contentRecord);
        $this->assertEquals('manual', $contentRecord->type); // brain_dump maps to 'manual' in database
        $this->assertContains('brain_dump', $contentRecord->tags); // but maintains brain_dump as tag
        $this->assertEquals($content, $contentRecord->content);
        $this->assertEquals($this->user->id, $contentRecord->user_id);

        // Test that AI extracted entities (the actual functionality we care about)
        $extractedEntities = $result['extracted_entities'];
        $this->assertIsArray($extractedEntities);
        $this->assertArrayHasKey('stakeholders', $extractedEntities);
        $this->assertArrayHasKey('workstreams', $extractedEntities);
        $this->assertArrayHasKey('releases', $extractedEntities);
        $this->assertArrayHasKey('action_items', $extractedEntities);
        $this->assertArrayHasKey('meetings', $extractedEntities);
        $this->assertArrayHasKey('decisions', $extractedEntities);

        // Test that entity matching is working
        $this->assertArrayHasKey('exact_matches', $result['match_results']);
        $this->assertArrayHasKey('fuzzy_matches', $result['match_results']);
        $this->assertArrayHasKey('new_entities', $result['match_results']);

        // Test that confirmation tasks are generated when appropriate
        $this->assertIsArray($result['confirmation_tasks']);
    }

    /**
     * Test Case: Content Type Processing - Email
     *
     * Given: An email content with metadata
     * When: The ContentProcessor processes the email
     * Then: It should extract entities and preserve email-specific metadata
     */
    public function test_processes_email_content_type()
    {
        $content = "From: alice@company.com\nSubject: Release Status Update\n\nThe mobile app release is delayed. Need to inform stakeholders and reschedule the deployment.";
        $metadata = [
            'from' => 'alice@company.com',
            'subject' => 'Release Status Update',
            'received_at' => '2024-01-22 10:30:00'
        ];

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Alice', 'confidence' => 0.95, 'context' => 'email sender', 'email' => 'alice@company.com']
            ],
            'releases' => [
                ['version' => 'mobile app release', 'confidence' => 0.90, 'context' => 'delayed release']
            ],
            'action_items' => [
                [
                    'text' => 'Inform stakeholders about delay',
                    'priority' => 'high',
                    'confidence' => 0.85
                ],
                [
                    'text' => 'Reschedule deployment',
                    'priority' => 'high',
                    'confidence' => 0.80
                ]
            ],
            'summary' => 'Mobile app release delayed, requires stakeholder communication and rescheduling'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'email', $metadata);

        $this->assertEquals('email', $result['content_record']->type);
        $this->assertEquals($metadata, $result['content_record']->metadata);
        $this->assertArrayHasKey('from', $result['content_record']->metadata);
        $this->assertCount(2, $result['extracted_entities']['action_items']);
    }

    /**
     * Test Case: Content Type Processing - Slack Message
     *
     * Given: A Slack message with channel and user information
     * When: The ContentProcessor processes the message
     * Then: It should handle Slack-specific formatting and extract entities
     */
    public function test_processes_slack_content_type()
    {
        $content = "@channel The API v2 workstream is blocked on database migrations. <@U123456> can you help? #urgent";
        $metadata = [
            'channel' => '#engineering',
            'user' => 'john.doe',
            'message_ts' => '1642857600.123456'
        ];

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'john.doe', 'confidence' => 0.95, 'context' => 'message author'],
                ['name' => 'U123456', 'confidence' => 0.90, 'context' => 'mentioned user']
            ],
            'workstreams' => [
                ['name' => 'API v2 workstream', 'confidence' => 0.95, 'context' => 'blocked workstream']
            ],
            'action_items' => [
                [
                    'text' => 'Help with database migrations for API v2 workstream',
                    'assignee' => 'U123456',
                    'priority' => 'high',
                    'confidence' => 0.85
                ]
            ],
            'summary' => 'API v2 workstream blocked on database migrations, help requested'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'slack', $metadata);

        $this->assertEquals('slack', $result['content_record']->type);
        $this->assertEquals('#engineering', $result['content_record']->metadata['channel']);
        $this->assertStringContainsString('blocked on database migrations', $result['extracted_entities']['summary']);
    }

    /**
     * Test Case: Content Type Processing - Document Upload
     *
     * Given: A document with file information
     * When: The ContentProcessor processes the document
     * Then: It should handle file-specific metadata and extract comprehensive entities
     */
    public function test_processes_document_content_type()
    {
        $content = "Project Requirements Document\n\nStakeholders: Product Team (Sarah, Mike), Engineering Team (Alex, Jennifer)\nRelease Target: Q2 2024\nKey Features:\n- User authentication\n- Payment processing\n- Admin dashboard";

        $metadata = [
            'file_name' => 'project_requirements.pdf',
            'file_size' => 2048576,
            'uploaded_at' => '2024-01-22 14:30:00'
        ];

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Sarah', 'confidence' => 0.95, 'context' => 'Product Team member'],
                ['name' => 'Mike', 'confidence' => 0.95, 'context' => 'Product Team member'],
                ['name' => 'Alex', 'confidence' => 0.95, 'context' => 'Engineering Team member'],
                ['name' => 'Jennifer', 'confidence' => 0.95, 'context' => 'Engineering Team member']
            ],
            'releases' => [
                ['version' => 'Q2 2024', 'confidence' => 0.90, 'context' => 'target release']
            ],
            'workstreams' => [
                ['name' => 'User authentication', 'confidence' => 0.85, 'context' => 'key feature'],
                ['name' => 'Payment processing', 'confidence' => 0.85, 'context' => 'key feature'],
                ['name' => 'Admin dashboard', 'confidence' => 0.85, 'context' => 'key feature']
            ],
            'summary' => 'Project requirements document outlining Q2 2024 release with key features and team assignments'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'document', $metadata);

        $this->assertEquals('document', $result['content_record']->type);
        $this->assertEquals('project_requirements.pdf', $result['content_record']->file_path);
        $this->assertEquals(2048576, $result['content_record']->file_size);
        $this->assertCount(4, $result['extracted_entities']['stakeholders']);
        $this->assertCount(3, $result['extracted_entities']['workstreams']);
    }

    /**
     * Test Case: Smart Entity Matching - Exact Match Found
     *
     * Given: Extracted stakeholder exists in database with exact name match
     * When: The ContentProcessor performs entity matching
     * Then: It should match to existing stakeholder with high confidence
     */
    public function test_matches_existing_stakeholder_exact_name()
    {
        $existingStakeholder = Stakeholder::factory()->create([
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@company.com',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'Sarah Johnson', 'confidence' => 0.95, 'context' => 'mentioned in meeting']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $this->assertArrayHasKey('stakeholders', $result);
        $this->assertCount(1, $result['stakeholders']['matches']);

        $match = $result['stakeholders']['matches'][0];
        $this->assertEquals($existingStakeholder->id, $match['existing_entity']->id);
        $this->assertEquals('exact_name', $match['match_type']);
        $this->assertGreaterThanOrEqual(0.95, $match['match_confidence']);
    }

    /**
     * Test Case: Smart Entity Matching - Fuzzy Match Found
     *
     * Given: Extracted stakeholder has similar name to existing stakeholder
     * When: The ContentProcessor performs entity matching
     * Then: It should suggest a fuzzy match with lower confidence
     */
    public function test_matches_existing_stakeholder_fuzzy_name()
    {
        $existingStakeholder = Stakeholder::factory()->create([
            'name' => 'Sarah Johnson',
            'email' => 'sarah.j@company.com',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'Sarah J', 'confidence' => 0.90, 'context' => 'mentioned in chat']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $match = $result['stakeholders']['matches'][0];
        $this->assertEquals($existingStakeholder->id, $match['existing_entity']->id);
        $this->assertEquals('fuzzy_name', $match['match_type']);
        $this->assertGreaterThanOrEqual(0.70, $match['match_confidence']);
        $this->assertLessThan(0.95, $match['match_confidence']);
    }

    /**
     * Test Case: Smart Entity Matching - Email Match Found
     *
     * Given: Extracted stakeholder includes email matching existing stakeholder
     * When: The ContentProcessor performs entity matching
     * Then: It should match by email with high confidence
     */
    public function test_matches_existing_stakeholder_by_email()
    {
        $existingStakeholder = Stakeholder::factory()->create([
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@company.com',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'Sarah J',
                    'email' => 'sarah.johnson@company.com',
                    'confidence' => 0.85,
                    'context' => 'email sender'
                ]
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $match = $result['stakeholders']['matches'][0];
        $this->assertEquals($existingStakeholder->id, $match['existing_entity']->id);
        $this->assertEquals('email', $match['match_type']);
        $this->assertGreaterThanOrEqual(0.95, $match['match_confidence']);
    }

    /**
     * Test Case: Smart Entity Matching - No Match Found
     *
     * Given: Extracted stakeholder does not match any existing stakeholders
     * When: The ContentProcessor performs entity matching
     * Then: It should suggest creating a new stakeholder
     */
    public function test_suggests_new_stakeholder_when_no_match()
    {
        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'New Person', 'confidence' => 0.90, 'context' => 'mentioned in meeting']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $this->assertCount(0, $result['stakeholders']['matches']);
        $this->assertCount(1, $result['stakeholders']['suggestions']);

        $suggestion = $result['stakeholders']['suggestions'][0];
        $this->assertEquals('create_new', $suggestion['action']);
        $this->assertEquals('New Person', $suggestion['data']['name']);
        $this->assertArrayHasKey('extracted_data', $suggestion);
    }

    /**
     * Test Case: Smart Entity Matching - Workstream Match by Name
     *
     * Given: Extracted workstream name matches existing workstream
     * When: The ContentProcessor performs entity matching
     * Then: It should match the existing workstream
     */
    public function test_matches_existing_workstream_by_name()
    {
        $existingWorkstream = Workstream::factory()->create([
            'name' => 'User Authentication System',
            'type' => 'initiative',
            'owner_id' => $this->user->id
        ]);

        $extractedEntities = [
            'workstreams' => [
                ['name' => 'User Authentication System', 'confidence' => 0.95, 'context' => 'needs updating']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $match = $result['workstreams']['matches'][0];
        $this->assertEquals($existingWorkstream->id, $match['existing_entity']->id);
        $this->assertEquals('exact_name', $match['match_type']);
        $this->assertGreaterThanOrEqual(0.95, $match['match_confidence']);
    }

    /**
     * Test Case: Smart Entity Matching - Release Match by Version
     *
     * Given: Extracted release version matches existing release
     * When: The ContentProcessor performs entity matching
     * Then: It should match the existing release
     */
    public function test_matches_existing_release_by_version()
    {
        $workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $existingRelease = Release::factory()->create([
            'name' => 'Q1 2024 Release',
            'version' => 'v2.1.0',
            'workstream_id' => $workstream->id
        ]);

        $extractedEntities = [
            'releases' => [
                ['version' => 'v2.1.0', 'confidence' => 0.90, 'context' => 'mentioned in discussion']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $match = $result['releases']['matches'][0];
        $this->assertEquals($existingRelease->id, $match['existing_entity']->id);
        $this->assertEquals('version', $match['match_type']);
        $this->assertGreaterThanOrEqual(0.90, $match['match_confidence']);
    }

    /**
     * Test Case: Confirmation Tasks Generation - Low Confidence Matches
     *
     * Given: Entity matches with confidence below threshold
     * When: The ContentProcessor generates confirmation tasks
     * Then: It should create tasks to confirm uncertain matches
     */
    public function test_generates_confirmation_tasks_for_low_confidence_matches()
    {
        $existingStakeholder = Stakeholder::factory()->create([
            'name' => 'John Smith',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'J. Smith', 'confidence' => 0.65, 'context' => 'briefly mentioned']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);
        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($result, $extractedEntities);

        $this->assertGreaterThan(0, count($confirmationTasks));

        $task = $confirmationTasks[0];
        $this->assertEquals('confirm_stakeholder_match', $task['type']);
        $this->assertEquals('J. Smith', $task['extracted_name']);
        $this->assertEquals('John Smith', $task['suggested_match']);
        $this->assertArrayHasKey('confidence_score', $task);
        $this->assertLessThan(0.70, $task['confidence_score']);
    }

    /**
     * Test Case: Confirmation Tasks Generation - Multiple Potential Matches
     *
     * Given: Single extracted entity matches multiple existing entities
     * When: The ContentProcessor generates confirmation tasks
     * Then: It should create a disambiguation task
     */
    public function test_generates_disambiguation_task_for_multiple_matches()
    {
        Stakeholder::factory()->create([
            'name' => 'John Smith',
            'email' => 'john.smith@company.com',
            'user_id' => $this->user->id
        ]);

        Stakeholder::factory()->create([
            'name' => 'John Smith',
            'email' => 'j.smith@company.com',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'John Smith', 'confidence' => 0.95, 'context' => 'mentioned in meeting']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);
        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($result, $extractedEntities);

        $disambiguationTask = collect($confirmationTasks)->firstWhere('type', 'disambiguate_stakeholder');

        $this->assertNotNull($disambiguationTask);
        $this->assertEquals('John Smith', $disambiguationTask['extracted_name']);
        $this->assertCount(2, $disambiguationTask['potential_matches']);
    }

    /**
     * Test Case: Confirmation Tasks Generation - New Entity Creation
     *
     * Given: High confidence extracted entity with no matches
     * When: The ContentProcessor generates confirmation tasks
     * Then: It should create a task to confirm new entity creation
     */
    public function test_generates_confirmation_task_for_new_entity_creation()
    {
        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'Alice Cooper',
                    'email' => 'alice.cooper@newcompany.com',
                    'confidence' => 0.95,
                    'context' => 'new team member introduction'
                ]
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);
        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($result, $extractedEntities);

        $creationTask = collect($confirmationTasks)->firstWhere('type', 'confirm_new_stakeholder');

        $this->assertNotNull($creationTask);
        $this->assertEquals('Alice Cooper', $creationTask['proposed_data']['name']);
        $this->assertEquals('alice.cooper@newcompany.com', $creationTask['proposed_data']['email']);
        $this->assertGreaterThanOrEqual(0.95, $creationTask['confidence_score']);
    }

    /**
     * Test Case: Action Item Processing with Entity Matching
     *
     * Given: Extracted action items with assignee names
     * When: The ContentProcessor processes action items
     * Then: It should match assignees to existing stakeholders and create ContentActionItem records
     */
    public function test_processes_action_items_with_stakeholder_matching()
    {
        $assigneeStakeholder = Stakeholder::factory()->create([
            'name' => 'Sarah Johnson',
            'user_id' => $this->user->id
        ]);

        $content = "Meeting notes: Sarah Johnson needs to review the API documentation by Friday.";

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Sarah Johnson', 'confidence' => 0.95, 'context' => 'assigned task']
            ],
            'action_items' => [
                [
                    'text' => 'Review the API documentation',
                    'assignee' => 'Sarah Johnson',
                    'priority' => 'high',
                    'due_date' => '2024-01-26',
                    'confidence' => 0.90,
                    'context' => 'assigned in meeting'
                ]
            ],
            'summary' => 'Action item assigned to Sarah Johnson'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'brain_dump');

        $this->assertDatabaseHas('content_action_items', [
            'content_id' => $result['content_record']->id,
            'action_text' => 'Review the API documentation',
            'assignee_stakeholder_id' => $assigneeStakeholder->id,
            'priority' => 'high',
            'due_date' => '2024-01-26'
        ]);
    }

    /**
     * Test Case: Content-Entity Relationship Storage
     *
     * Given: Processed content with matched entities
     * When: The ContentProcessor stores relationships
     * Then: It should create pivot table relationships with confidence scores
     */
    public function test_stores_content_entity_relationships_with_confidence()
    {
        $stakeholder = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);

        $content = "Discussion with {$stakeholder->name} about {$workstream->name} progress.";

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => $stakeholder->name, 'confidence' => 0.95, 'context' => 'discussion participant']
            ],
            'workstreams' => [
                ['name' => $workstream->name, 'confidence' => 0.90, 'context' => 'topic of discussion']
            ],
            'summary' => 'Progress discussion'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'brain_dump');

        $this->assertDatabaseHas('content_stakeholders', [
            'content_id' => $result['content_record']->id,
            'stakeholder_id' => $stakeholder->id,
            'confidence_score' => 0.95,
            'context' => 'discussion participant'
        ]);

        $this->assertDatabaseHas('content_workstreams', [
            'content_id' => $result['content_record']->id,
            'workstream_id' => $workstream->id,
            'confidence_score' => 0.90,
            'context' => 'topic of discussion'
        ]);
    }

    /**
     * Test Case: Merge Entity Data with Existing Records
     *
     * Given: Extracted entity data contains additional information
     * When: The ContentProcessor matches to existing entity
     * Then: It should suggest merging new data with existing entity
     */
    public function test_suggests_merging_additional_entity_data()
    {
        $existingStakeholder = Stakeholder::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@company.com',
            'phone' => null,
            'title' => null,
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'John Doe',
                    'email' => 'john@company.com',
                    'phone' => '+1-555-123-4567',
                    'title' => 'Senior Engineer',
                    'confidence' => 0.95,
                    'context' => 'contact details provided'
                ]
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);
        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($result, $extractedEntities);

        $mergeTask = collect($confirmationTasks)->firstWhere('type', 'merge_stakeholder_data');

        $this->assertNotNull($mergeTask);
        $this->assertEquals($existingStakeholder->id, $mergeTask['existing_entity_id']);
        $this->assertArrayHasKey('phone', $mergeTask['new_data']);
        $this->assertArrayHasKey('title', $mergeTask['new_data']);
        $this->assertEquals('+1-555-123-4567', $mergeTask['new_data']['phone']);
        $this->assertEquals('Senior Engineer', $mergeTask['new_data']['title']);
    }

    /**
     * Test Case: Error Handling - AI Service Failure
     *
     * Given: AI service throws an exception
     * When: The ContentProcessor attempts to process content
     * Then: It should handle the error gracefully and return appropriate error response
     */
    public function test_handles_ai_service_failure_gracefully()
    {
        $content = "This is some content to process.";

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andThrow(new \Exception('AI service is temporarily unavailable'));

        $result = $this->contentProcessor->process($content, 'brain_dump');

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('ai_service_error', $result['error']['type']);
        $this->assertStringContainsString('AI service is temporarily unavailable', $result['error']['message']);
        $this->assertArrayHasKey('content_record', $result);
        $this->assertEquals('failed', $result['content_record']->status);
    }

    /**
     * Test Case: Error Handling - Invalid Content Type
     *
     * Given: An unsupported content type
     * When: The ContentProcessor attempts to process content
     * Then: It should throw a validation exception
     */
    public function test_throws_exception_for_invalid_content_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported content type: invalid_type');

        $this->contentProcessor->process("Some content", 'invalid_type');
    }

    /**
     * Test Case: Error Handling - Empty Content
     *
     * Given: Empty or whitespace-only content
     * When: The ContentProcessor attempts to process content
     * Then: It should throw a validation exception
     */
    public function test_throws_exception_for_empty_content()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content cannot be empty');

        $this->contentProcessor->process("   ", 'brain_dump');
    }

    /**
     * Test Case: Error Handling - Content Too Large
     *
     * Given: Content exceeding maximum allowed size
     * When: The ContentProcessor attempts to process content
     * Then: It should throw a validation exception
     */
    public function test_throws_exception_for_oversized_content()
    {
        $largeContent = str_repeat("This is a very long content string. ", 1000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content exceeds maximum allowed length');

        $this->contentProcessor->process($largeContent, 'brain_dump');
    }

    /**
     * Test Case: Performance - Batch Processing
     *
     * Given: Multiple content items to process
     * When: The ContentProcessor processes them in batch
     * Then: It should optimize AI calls and database operations
     */
    public function test_optimizes_batch_processing()
    {
        $contents = [
            'First brain dump content with action items.',
            'Second content mentioning Sarah from marketing.',
            'Third content about Q1 release planning.'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->times(3)
            ->andReturn([
                'stakeholders' => [],
                'workstreams' => [],
                'releases' => [],
                'action_items' => [],
                'summary' => 'Processed content'
            ]);

        $results = $this->contentProcessor->processBatch($contents, 'brain_dump');

        $this->assertCount(3, $results);
        $this->assertDatabaseCount('contents', 3);

        foreach ($results as $result) {
            $this->assertArrayHasKey('content_record', $result);
            $this->assertEquals('processed', $result['content_record']->status);
        }
    }

    /**
     * Test Case: Entity Relationship Inference
     *
     * Given: Action items mentioning workstreams and releases
     * When: The ContentProcessor processes the content
     * Then: It should infer relationships between action items and related entities
     */
    public function test_infers_action_item_entity_relationships()
    {
        $workstream = Workstream::factory()->create(['name' => 'Payment System', 'owner_id' => $this->user->id]);
        $release = Release::factory()->create(['name' => 'Q2 Release', 'workstream_id' => $workstream->id]);

        $content = "Need to fix the payment validation bug for Q2 Release in the Payment System workstream.";

        $mockAiResponse = [
            'workstreams' => [
                ['name' => 'Payment System', 'confidence' => 0.95, 'context' => 'bug location']
            ],
            'releases' => [
                ['name' => 'Q2 Release', 'confidence' => 0.90, 'context' => 'target release']
            ],
            'action_items' => [
                [
                    'text' => 'Fix payment validation bug',
                    'priority' => 'high',
                    'confidence' => 0.95,
                    'context' => 'bug fix required'
                ]
            ],
            'summary' => 'Bug fix required for payment system'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'brain_dump');

        $actionItem = ContentActionItem::where('content_id', $result['content_record']->id)->first();

        $this->assertNotNull($actionItem);
        $this->assertTrue($actionItem->workstreams->contains($workstream));
        $this->assertTrue($actionItem->releases->contains($release));
    }

    /**
     * Test Case: Context-Aware Entity Extraction
     *
     * Given: Content with context clues about entity types
     * When: The ContentProcessor analyzes the content
     * Then: It should use context to improve entity classification accuracy
     */
    public function test_uses_context_for_entity_classification()
    {
        $content = "Email from sarah@company.com about the mobile-app workstream: The v2.1 release is ready for testing.";

        $mockAiResponse = [
            'stakeholders' => [
                [
                    'name' => 'Sarah',
                    'email' => 'sarah@company.com',
                    'confidence' => 0.95,
                    'context' => 'email sender, high confidence due to email address'
                ]
            ],
            'workstreams' => [
                [
                    'name' => 'mobile-app',
                    'confidence' => 0.90,
                    'context' => 'explicitly mentioned as workstream'
                ]
            ],
            'releases' => [
                [
                    'version' => 'v2.1',
                    'confidence' => 0.85,
                    'context' => 'version number format indicates release'
                ]
            ],
            'summary' => 'Release readiness notification from stakeholder'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'email');

        $stakeholder = $result['extracted_entities']['stakeholders'][0];
        $this->assertStringContainsString('high confidence due to email address', $stakeholder['context']);

        $workstream = $result['extracted_entities']['workstreams'][0];
        $this->assertStringContainsString('explicitly mentioned as workstream', $workstream['context']);
    }

    /**
     * Test Case: Integration with Existing BrainDumpProcessor Functionality
     *
     * Given: Content that would be processed by BrainDumpProcessor
     * When: The ContentProcessor processes the same content
     * Then: It should provide enhanced functionality while maintaining compatibility
     */
    public function test_maintains_compatibility_with_brain_dump_processor()
    {
        $content = "Had a quick sync with the team. Need to follow up on the user auth issue by Friday.";

        $mockAiResponse = [
            'stakeholders' => [],
            'workstreams' => [
                ['name' => 'user auth', 'confidence' => 0.80, 'context' => 'issue mentioned']
            ],
            'releases' => [],
            'action_items' => [
                [
                    'text' => 'Follow up on user auth issue',
                    'priority' => 'medium',
                    'due_date' => '2024-01-26',
                    'confidence' => 0.85,
                    'context' => 'follow-up task'
                ]
            ],
            'summary' => 'Team sync with follow-up action item'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($content, 'brain_dump');

        // Verify it creates the same structures as BrainDumpProcessor would expect
        $this->assertArrayHasKey('extracted_entities', $result);
        $this->assertArrayHasKey('action_items', $result['extracted_entities']);
        $this->assertArrayHasKey('content_record', $result);

        // Verify enhanced functionality
        $this->assertArrayHasKey('entity_matches', $result);
        $this->assertArrayHasKey('confirmation_tasks', $result);
    }

    /**
     * Test Case: Duplicate Content Detection
     *
     * Given: Content similar to previously processed content
     * When: The ContentProcessor processes the content
     * Then: It should detect potential duplicates and suggest consolidation
     */
    public function test_detects_potential_duplicate_content()
    {
        // Create existing content
        $existingContent = Content::factory()->create([
            'content' => 'Meeting with Sarah about Q1 release planning and action items.',
            'user_id' => $this->user->id,
            'type' => 'brain_dump'
        ]);

        $similarContent = 'Had a meeting with Sarah regarding Q1 release planning. Several action items were discussed.';

        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'Sarah', 'confidence' => 0.95, 'context' => 'meeting participant']
            ],
            'releases' => [
                ['version' => 'Q1 release', 'confidence' => 0.90, 'context' => 'planning topic']
            ],
            'action_items' => [],
            'summary' => 'Meeting about Q1 release planning'
        ];

        $this->mockAiService
            ->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($mockAiResponse);

        $result = $this->contentProcessor->process($similarContent, 'brain_dump');

        $this->assertArrayHasKey('potential_duplicates', $result);
        $this->assertGreaterThan(0, count($result['potential_duplicates']));

        $duplicate = $result['potential_duplicates'][0];
        $this->assertEquals($existingContent->id, $duplicate['existing_content_id']);
        $this->assertGreaterThan(0.7, $duplicate['similarity_score']);
    }
}