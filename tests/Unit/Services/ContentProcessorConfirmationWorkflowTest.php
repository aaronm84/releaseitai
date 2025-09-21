<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ContentProcessor;
use App\Services\AiService;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Focused tests for ContentProcessor confirmation workflow generation
 * These tests validate the intelligent generation of confirmation tasks
 * when entity matching confidence is below thresholds or requires user input
 */
class ContentProcessorConfirmationWorkflowTest extends TestCase
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
     * Test Case: Confirmation Task - Low Confidence Stakeholder Match
     *
     * Given: Stakeholder match with confidence below threshold (70%)
     * When: ContentProcessor generates confirmation tasks
     * Then: It should create a stakeholder confirmation task with match details
     */
    public function test_generates_stakeholder_confirmation_for_low_confidence_match()
    {
        $existingStakeholder = Stakeholder::factory()->create([
            'name' => 'John Alexander Smith',
            'email' => 'john.smith@company.com',
            'title' => 'Senior Developer',
            'user_id' => $this->user->id
        ]);

        $matchResults = [
            'stakeholders' => [
                'matches' => [
                    [
                        'existing_entity' => $existingStakeholder,
                        'extracted_data' => [
                            'name' => 'J. Smith',
                            'confidence' => 0.85,
                            'context' => 'briefly mentioned in notes'
                        ],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.65
                    ]
                ],
                'suggestions' => []
            ]
        ];

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'J. Smith', 'confidence' => 0.85, 'context' => 'briefly mentioned in notes']
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        $this->assertGreaterThan(0, count($confirmationTasks));

        $stakeholderTask = collect($confirmationTasks)->firstWhere('type', 'confirm_stakeholder_match');
        $this->assertNotNull($stakeholderTask);

        $this->assertEquals('J. Smith', $stakeholderTask['extracted_name']);
        $this->assertEquals($existingStakeholder->id, $stakeholderTask['suggested_match_id']);
        $this->assertEquals('John Alexander Smith', $stakeholderTask['suggested_match_name']);
        $this->assertEquals(0.65, $stakeholderTask['match_confidence']);
        $this->assertEquals('low_confidence', $stakeholderTask['reason']);
        $this->assertArrayHasKey('additional_context', $stakeholderTask);
        $this->assertEquals('john.smith@company.com', $stakeholderTask['additional_context']['email']);
        $this->assertEquals('Senior Developer', $stakeholderTask['additional_context']['title']);
    }

    /**
     * Test Case: Confirmation Task - Multiple Potential Stakeholder Matches
     *
     * Given: Multiple stakeholders with identical names
     * When: ContentProcessor generates confirmation tasks
     * Then: It should create a disambiguation task with all potential matches
     */
    public function test_generates_disambiguation_task_for_multiple_stakeholder_matches()
    {
        $stakeholder1 = Stakeholder::factory()->create([
            'name' => 'Michael Johnson',
            'email' => 'michael.johnson@companyA.com',
            'company' => 'Company A',
            'title' => 'Product Manager',
            'user_id' => $this->user->id
        ]);

        $stakeholder2 = Stakeholder::factory()->create([
            'name' => 'Michael Johnson',
            'email' => 'mike.johnson@companyB.com',
            'company' => 'Company B',
            'title' => 'Engineering Manager',
            'user_id' => $this->user->id
        ]);

        $stakeholder3 = Stakeholder::factory()->create([
            'name' => 'Michael Johnson',
            'email' => 'mj@freelance.com',
            'company' => 'Freelance',
            'title' => 'Consultant',
            'user_id' => $this->user->id
        ]);

        $matchResults = [
            'stakeholders' => [
                'matches' => [
                    [
                        'existing_entity' => $stakeholder1,
                        'extracted_data' => ['name' => 'Michael Johnson', 'confidence' => 0.95],
                        'match_type' => 'exact_name',
                        'match_confidence' => 0.95
                    ],
                    [
                        'existing_entity' => $stakeholder2,
                        'extracted_data' => ['name' => 'Michael Johnson', 'confidence' => 0.95],
                        'match_type' => 'exact_name',
                        'match_confidence' => 0.95
                    ],
                    [
                        'existing_entity' => $stakeholder3,
                        'extracted_data' => ['name' => 'Michael Johnson', 'confidence' => 0.95],
                        'match_type' => 'exact_name',
                        'match_confidence' => 0.95
                    ]
                ],
                'suggestions' => [],
                'requires_disambiguation' => true
            ]
        ];

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'Michael Johnson', 'confidence' => 0.95, 'context' => 'meeting attendee']
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        $disambiguationTask = collect($confirmationTasks)->firstWhere('type', 'disambiguate_stakeholder');
        $this->assertNotNull($disambiguationTask);

        $this->assertEquals('Michael Johnson', $disambiguationTask['extracted_name']);
        $this->assertCount(3, $disambiguationTask['potential_matches']);

        // Verify each potential match has required disambiguation details
        foreach ($disambiguationTask['potential_matches'] as $match) {
            $this->assertArrayHasKey('id', $match);
            $this->assertArrayHasKey('name', $match);
            $this->assertArrayHasKey('email', $match);
            $this->assertArrayHasKey('company', $match);
            $this->assertArrayHasKey('title', $match);
        }

        $this->assertEquals('meeting attendee', $disambiguationTask['context']);
        $this->assertEquals('multiple_exact_matches', $disambiguationTask['reason']);
    }

    /**
     * Test Case: Confirmation Task - New Stakeholder Creation with High Confidence
     *
     * Given: Extracted stakeholder with high confidence but no existing matches
     * When: ContentProcessor generates confirmation tasks
     * Then: It should create a new stakeholder confirmation task with extracted data
     */
    public function test_generates_new_stakeholder_confirmation_task()
    {
        $matchResults = [
            'stakeholders' => [
                'matches' => [],
                'suggestions' => [
                    [
                        'action' => 'create_new',
                        'extracted_data' => [
                            'name' => 'Alice Cooper',
                            'email' => 'alice.cooper@newcompany.com',
                            'title' => 'Senior Designer',
                            'company' => 'NewCompany Inc',
                            'confidence' => 0.95,
                            'context' => 'introduced in team meeting'
                        ],
                        'confidence' => 0.95
                    ]
                ]
            ]
        ];

        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'Alice Cooper',
                    'email' => 'alice.cooper@newcompany.com',
                    'title' => 'Senior Designer',
                    'company' => 'NewCompany Inc',
                    'confidence' => 0.95,
                    'context' => 'introduced in team meeting'
                ]
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        $newStakeholderTask = collect($confirmationTasks)->firstWhere('type', 'confirm_new_stakeholder');
        $this->assertNotNull($newStakeholderTask);

        $this->assertEquals('Alice Cooper', $newStakeholderTask['proposed_data']['name']);
        $this->assertEquals('alice.cooper@newcompany.com', $newStakeholderTask['proposed_data']['email']);
        $this->assertEquals('Senior Designer', $newStakeholderTask['proposed_data']['title']);
        $this->assertEquals('NewCompany Inc', $newStakeholderTask['proposed_data']['company']);
        $this->assertEquals(0.95, $newStakeholderTask['confidence_score']);
        $this->assertEquals('introduced in team meeting', $newStakeholderTask['context']);
        $this->assertEquals('high_confidence_new_entity', $newStakeholderTask['reason']);
    }

    /**
     * Test Case: Confirmation Task - Merge Additional Stakeholder Data
     *
     * Given: Existing stakeholder match with additional extracted information
     * When: ContentProcessor generates confirmation tasks
     * Then: It should create a merge data confirmation task
     */
    public function test_generates_merge_data_confirmation_task()
    {
        $existingStakeholder = Stakeholder::factory()->create([
            'name' => 'David Wilson',
            'email' => 'david.wilson@company.com',
            'phone' => null,
            'title' => null,
            'linkedin_handle' => null,
            'user_id' => $this->user->id
        ]);

        $matchResults = [
            'stakeholders' => [
                'matches' => [
                    [
                        'existing_entity' => $existingStakeholder,
                        'extracted_data' => [
                            'name' => 'David Wilson',
                            'email' => 'david.wilson@company.com',
                            'phone' => '+1-555-987-6543',
                            'title' => 'Lead Product Manager',
                            'linkedin_handle' => 'davidwilson-pm',
                            'confidence' => 0.95
                        ],
                        'match_type' => 'exact_email',
                        'match_confidence' => 0.98,
                        'has_additional_data' => true,
                        'new_data' => [
                            'phone' => '+1-555-987-6543',
                            'title' => 'Lead Product Manager',
                            'linkedin_handle' => 'davidwilson-pm'
                        ]
                    ]
                ],
                'suggestions' => []
            ]
        ];

        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'David Wilson',
                    'email' => 'david.wilson@company.com',
                    'phone' => '+1-555-987-6543',
                    'title' => 'Lead Product Manager',
                    'linkedin_handle' => 'davidwilson-pm',
                    'confidence' => 0.95,
                    'context' => 'contact details shared'
                ]
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        $mergeTask = collect($confirmationTasks)->firstWhere('type', 'merge_stakeholder_data');
        $this->assertNotNull($mergeTask);

        $this->assertEquals($existingStakeholder->id, $mergeTask['existing_entity_id']);
        $this->assertEquals('David Wilson', $mergeTask['existing_entity_name']);
        $this->assertArrayHasKey('new_data', $mergeTask);
        $this->assertEquals('+1-555-987-6543', $mergeTask['new_data']['phone']);
        $this->assertEquals('Lead Product Manager', $mergeTask['new_data']['title']);
        $this->assertEquals('davidwilson-pm', $mergeTask['new_data']['linkedin_handle']);
        $this->assertEquals('additional_data_available', $mergeTask['reason']);
    }

    /**
     * Test Case: Confirmation Task - Workstream Disambiguation
     *
     * Given: Multiple workstreams with similar names
     * When: ContentProcessor generates confirmation tasks
     * Then: It should create workstream disambiguation task with hierarchical context
     */
    public function test_generates_workstream_disambiguation_task()
    {
        $parentWorkstream = Workstream::factory()->create([
            'name' => 'Mobile Platform',
            'type' => 'product_line',
            'owner_id' => $this->user->id
        ]);

        $childWorkstream1 = Workstream::factory()->create([
            'name' => 'Mobile Authentication',
            'type' => 'initiative',
            'parent_workstream_id' => $parentWorkstream->id,
            'owner_id' => $this->user->id
        ]);

        $childWorkstream2 = Workstream::factory()->create([
            'name' => 'Mobile Analytics',
            'type' => 'initiative',
            'parent_workstream_id' => $parentWorkstream->id,
            'owner_id' => $this->user->id
        ]);

        $matchResults = [
            'workstreams' => [
                'matches' => [
                    [
                        'existing_entity' => $childWorkstream1,
                        'extracted_data' => ['name' => 'Mobile Auth', 'confidence' => 0.80],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.80
                    ],
                    [
                        'existing_entity' => $childWorkstream2,
                        'extracted_data' => ['name' => 'Mobile Analytics', 'confidence' => 0.75],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.75
                    ]
                ],
                'suggestions' => [],
                'requires_disambiguation' => true
            ]
        ];

        $extractedEntities = [
            'workstreams' => [
                ['name' => 'Mobile team project', 'confidence' => 0.80, 'context' => 'mentioned in planning']
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        $workstreamTask = collect($confirmationTasks)->firstWhere('type', 'disambiguate_workstream');
        $this->assertNotNull($workstreamTask);

        $this->assertEquals('Mobile team project', $workstreamTask['extracted_name']);
        $this->assertCount(2, $workstreamTask['potential_matches']);

        foreach ($workstreamTask['potential_matches'] as $match) {
            $this->assertArrayHasKey('id', $match);
            $this->assertArrayHasKey('name', $match);
            $this->assertArrayHasKey('type', $match);
            $this->assertArrayHasKey('hierarchy_path', $match);
        }

        $this->assertStringContainsString('Mobile Platform > Mobile Authentication',
            $workstreamTask['potential_matches'][0]['hierarchy_path']);
        $this->assertStringContainsString('Mobile Platform > Mobile Analytics',
            $workstreamTask['potential_matches'][1]['hierarchy_path']);
    }

    /**
     * Test Case: Confirmation Task - Action Item Assignment Verification
     *
     * Given: Action item with uncertain assignee match
     * When: ContentProcessor generates confirmation tasks
     * Then: It should create action item assignment confirmation task
     */
    public function test_generates_action_item_assignment_confirmation()
    {
        $stakeholder = Stakeholder::factory()->create([
            'name' => 'Jennifer Martinez',
            'email' => 'jennifer.martinez@company.com',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'action_items' => [
                [
                    'text' => 'Review API documentation and provide feedback',
                    'assignee' => 'Jen M',
                    'priority' => 'high',
                    'due_date' => '2024-01-30',
                    'confidence' => 0.85,
                    'context' => 'assigned during meeting'
                ]
            ],
            'stakeholders' => [
                ['name' => 'Jen M', 'confidence' => 0.70, 'context' => 'assignee mention']
            ]
        ];

        $matchResults = [
            'stakeholders' => [
                'matches' => [
                    [
                        'existing_entity' => $stakeholder,
                        'extracted_data' => ['name' => 'Jen M', 'confidence' => 0.70],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.68
                    ]
                ],
                'suggestions' => []
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        $actionItemTask = collect($confirmationTasks)->firstWhere('type', 'confirm_action_item_assignment');
        $this->assertNotNull($actionItemTask);

        $this->assertEquals('Review API documentation and provide feedback', $actionItemTask['action_item_text']);
        $this->assertEquals('Jen M', $actionItemTask['extracted_assignee']);
        $this->assertEquals($stakeholder->id, $actionItemTask['suggested_assignee_id']);
        $this->assertEquals('Jennifer Martinez', $actionItemTask['suggested_assignee_name']);
        $this->assertEquals(0.68, $actionItemTask['assignment_confidence']);
        $this->assertEquals('low_confidence_assignee_match', $actionItemTask['reason']);
    }

    /**
     * Test Case: Confirmation Task - Priority and Urgency Assessment
     *
     * Given: Mixed confidence levels and urgency indicators
     * When: ContentProcessor generates confirmation tasks
     * Then: It should prioritize tasks based on confidence levels and business impact
     */
    public function test_prioritizes_confirmation_tasks_by_urgency_and_confidence()
    {
        // Setup multiple scenarios requiring confirmation
        $stakeholder1 = Stakeholder::factory()->create(['name' => 'High Priority Person', 'user_id' => $this->user->id]);
        $stakeholder2 = Stakeholder::factory()->create(['name' => 'Medium Priority Person', 'user_id' => $this->user->id]);

        $matchResults = [
            'stakeholders' => [
                'matches' => [
                    [
                        'existing_entity' => $stakeholder1,
                        'extracted_data' => [
                            'name' => 'HP Person',
                            'confidence' => 0.60,
                            'context' => 'CEO mentioned in urgent email'
                        ],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.60
                    ],
                    [
                        'existing_entity' => $stakeholder2,
                        'extracted_data' => [
                            'name' => 'MP Person',
                            'confidence' => 0.65,
                            'context' => 'casual mention in notes'
                        ],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.65
                    ]
                ],
                'suggestions' => [
                    [
                        'action' => 'create_new',
                        'extracted_data' => [
                            'name' => 'New VIP',
                            'confidence' => 0.95,
                            'context' => 'board member introduction'
                        ]
                    ]
                ]
            ]
        ];

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'HP Person', 'confidence' => 0.60, 'context' => 'CEO mentioned in urgent email'],
                ['name' => 'MP Person', 'confidence' => 0.65, 'context' => 'casual mention in notes'],
                ['name' => 'New VIP', 'confidence' => 0.95, 'context' => 'board member introduction']
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        $this->assertGreaterThan(2, count($confirmationTasks));

        // Check that tasks are sorted by priority
        $priorities = array_column($confirmationTasks, 'priority');
        $this->assertContains('critical', $priorities); // Board member introduction
        $this->assertContains('high', $priorities);     // CEO mention
        $this->assertContains('medium', $priorities);   // Casual mention

        // Verify the critical priority task
        $criticalTask = collect($confirmationTasks)->firstWhere('priority', 'critical');
        $this->assertNotNull($criticalTask);
        $this->assertStringContainsString('board member', $criticalTask['context']);

        // Verify priority reasoning
        $highPriorityTask = collect($confirmationTasks)->firstWhere('priority', 'high');
        $this->assertNotNull($highPriorityTask);
        $this->assertStringContainsString('CEO', $highPriorityTask['context']);
    }

    /**
     * Test Case: Confirmation Task - Complex Multi-Entity Relationships
     *
     * Given: Extracted content with interconnected entities requiring confirmation
     * When: ContentProcessor generates confirmation tasks
     * Then: It should create grouped confirmation tasks for related entities
     */
    public function test_groups_related_entity_confirmations()
    {
        $workstream = Workstream::factory()->create([
            'name' => 'Product Launch Initiative',
            'owner_id' => $this->user->id
        ]);

        $release = Release::factory()->create([
            'name' => 'Q2 Product Launch',
            'workstream_id' => $workstream->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'Launch Team Lead',
                    'confidence' => 0.90,
                    'context' => 'mentioned as Q2 launch coordinator'
                ]
            ],
            'workstreams' => [
                [
                    'name' => 'Product Launch',
                    'confidence' => 0.80,
                    'context' => 'team working on Q2 launch'
                ]
            ],
            'releases' => [
                [
                    'version' => 'Q2 Launch',
                    'confidence' => 0.85,
                    'context' => 'scheduled for Q2 2024'
                ]
            ],
            'action_items' => [
                [
                    'text' => 'Coordinate launch timeline with team lead',
                    'assignee' => 'Launch Team Lead',
                    'priority' => 'high',
                    'confidence' => 0.90,
                    'context' => 'critical for Q2 launch success'
                ]
            ]
        ];

        $matchResults = [
            'stakeholders' => [
                'matches' => [],
                'suggestions' => [
                    [
                        'action' => 'create_new',
                        'extracted_data' => $extractedEntities['stakeholders'][0]
                    ]
                ]
            ],
            'workstreams' => [
                'matches' => [
                    [
                        'existing_entity' => $workstream,
                        'extracted_data' => $extractedEntities['workstreams'][0],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.75
                    ]
                ]
            ],
            'releases' => [
                'matches' => [
                    [
                        'existing_entity' => $release,
                        'extracted_data' => $extractedEntities['releases'][0],
                        'match_type' => 'fuzzy_name',
                        'match_confidence' => 0.80
                    ]
                ]
            ]
        ];

        $confirmationTasks = $this->contentProcessor->generateConfirmationTasks($matchResults, $extractedEntities);

        // Should have a grouped confirmation task
        $groupedTask = collect($confirmationTasks)->firstWhere('type', 'confirm_entity_group');
        $this->assertNotNull($groupedTask);

        $this->assertEquals('Q2 Product Launch Context', $groupedTask['group_name']);
        $this->assertArrayHasKey('related_entities', $groupedTask);
        $this->assertCount(3, $groupedTask['related_entities']); // Stakeholder, workstream, release

        // Verify entity relationships are preserved
        $this->assertArrayHasKey('stakeholder_workstream_relations', $groupedTask);
        $this->assertArrayHasKey('action_item_dependencies', $groupedTask);
    }

    /**
     * Test Case: Confirmation Task - Batch Processing Optimization
     *
     * Given: Multiple content items processed in batch with overlapping entities
     * When: ContentProcessor generates confirmation tasks
     * Then: It should consolidate similar confirmation tasks to reduce user burden
     */
    public function test_consolidates_similar_confirmation_tasks_in_batch()
    {
        $stakeholder = Stakeholder::factory()->create([
            'name' => 'Sarah Johnson',
            'user_id' => $this->user->id
        ]);

        // Simulate batch processing with similar entity mentions
        $batchMatchResults = [
            [
                'stakeholders' => [
                    'matches' => [
                        [
                            'existing_entity' => $stakeholder,
                            'extracted_data' => ['name' => 'S. Johnson', 'confidence' => 0.65],
                            'match_type' => 'fuzzy_name',
                            'match_confidence' => 0.65
                        ]
                    ]
                ]
            ],
            [
                'stakeholders' => [
                    'matches' => [
                        [
                            'existing_entity' => $stakeholder,
                            'extracted_data' => ['name' => 'Sarah J', 'confidence' => 0.68],
                            'match_type' => 'fuzzy_name',
                            'match_confidence' => 0.68
                        ]
                    ]
                ]
            ]
        ];

        $batchExtractedEntities = [
            [
                'stakeholders' => [
                    ['name' => 'S. Johnson', 'confidence' => 0.65, 'context' => 'email mention']
                ]
            ],
            [
                'stakeholders' => [
                    ['name' => 'Sarah J', 'confidence' => 0.68, 'context' => 'meeting notes']
                ]
            ]
        ];

        $consolidatedTasks = $this->contentProcessor->generateBatchConfirmationTasks(
            $batchMatchResults,
            $batchExtractedEntities
        );

        // Should consolidate similar stakeholder confirmations
        $stakeholderTasks = collect($consolidatedTasks)->where('type', 'confirm_stakeholder_match_batch');
        $this->assertCount(1, $stakeholderTasks); // Consolidated into one task

        $consolidatedTask = $stakeholderTasks->first();
        $this->assertEquals($stakeholder->id, $consolidatedTask['stakeholder_id']);
        $this->assertCount(2, $consolidatedTask['name_variations']); // S. Johnson and Sarah J
        $this->assertEquals(['email mention', 'meeting notes'], $consolidatedTask['contexts']);
    }
}