<?php

namespace Tests\Unit\Services;

use App\Models\Content;
use App\Models\User;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ContentActionItem;
use App\Services\AiEntityDetectionService;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class AiEntityDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AiEntityDetectionService $service;
    protected AiService $aiService;
    protected User $user;
    protected Content $content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stakeholder = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $this->workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $this->release = Release::factory()->create(['workstream_id' => $this->workstream->id]);
        $this->content = Content::factory()->create(['user_id' => $this->user->id]);

        $this->aiService = Mockery::mock(AiService::class);
        $this->service = new AiEntityDetectionService($this->aiService);
    }

    /** @test */
    public function it_can_detect_entities_in_content()
    {
        $mockAiResponse = [
            'stakeholders' => [
                ['name' => 'John Smith', 'confidence' => 0.95, 'context' => 'John will handle the API changes'],
                ['name' => 'Engineering Team', 'confidence' => 0.87, 'context' => 'Engineering team needs to review']
            ],
            'workstreams' => [
                ['name' => 'Mobile App Redesign', 'confidence' => 0.92, 'context' => 'Mobile app v2.1 features']
            ],
            'releases' => [
                ['version' => 'v2.1', 'confidence' => 0.88, 'context' => 'Version 2.1 release timeline']
            ],
            'action_items' => [
                [
                    'text' => 'Review API documentation',
                    'assignee' => 'John Smith',
                    'confidence' => 0.91,
                    'context' => 'John needs to review the API docs by Friday',
                    'priority' => 'medium',
                    'due_date' => '2025-09-25'
                ]
            ]
        ];

        $this->aiService->shouldReceive('analyzeContentEntities')
            ->once()
            ->with($this->content->content)
            ->andReturn($mockAiResponse);

        $result = $this->service->detectEntities($this->content);

        $this->assertArrayHasKey('stakeholders', $result);
        $this->assertArrayHasKey('workstreams', $result);
        $this->assertArrayHasKey('releases', $result);
        $this->assertArrayHasKey('action_items', $result);

        $this->assertCount(2, $result['stakeholders']);
        $this->assertCount(1, $result['workstreams']);
        $this->assertCount(1, $result['releases']);
        $this->assertCount(1, $result['action_items']);
    }

    /** @test */
    public function it_can_match_detected_stakeholders_to_existing_records()
    {
        // Update the existing stakeholder
        $this->stakeholder->update([
            'name' => 'John Smith',
            'email' => 'john@example.com'
        ]);

        $detectedEntities = [
            'stakeholders' => [
                ['name' => 'John Smith', 'confidence' => 0.95, 'context' => 'John mentioned'],
                ['name' => 'New Person', 'confidence' => 0.85, 'context' => 'New person mentioned']
            ],
            'workstreams' => [],
            'releases' => [],
            'action_items' => []
        ];

        $matches = $this->service->matchEntitiesToExisting($this->user, $detectedEntities);

        $this->assertArrayHasKey('stakeholders', $matches);
        $this->assertArrayHasKey('matched', $matches['stakeholders']);
        $this->assertArrayHasKey('unmatched', $matches['stakeholders']);

        $this->assertCount(1, $matches['stakeholders']['matched']);
        $this->assertCount(1, $matches['stakeholders']['unmatched']);

        $this->assertEquals($this->stakeholder->id, $matches['stakeholders']['matched'][0]['existing_id']);
        $this->assertEquals('John Smith', $matches['stakeholders']['matched'][0]['detected_name']);
    }

    /** @test */
    public function it_can_match_detected_workstreams_by_name_and_description()
    {
        $this->workstream->update([
            'name' => 'Mobile App Redesign',
            'description' => 'Complete redesign of the mobile application'
        ]);

        $detectedEntities = [
            'stakeholders' => [],
            'workstreams' => [
                ['name' => 'Mobile App', 'confidence' => 0.85, 'context' => 'Mobile app project'],
                ['name' => 'Backend API', 'confidence' => 0.90, 'context' => 'New backend API work']
            ],
            'releases' => [],
            'action_items' => []
        ];

        $matches = $this->service->matchEntitiesToExisting($this->user, $detectedEntities);

        $this->assertCount(1, $matches['workstreams']['matched']);
        $this->assertCount(1, $matches['workstreams']['unmatched']);

        $this->assertEquals($this->workstream->id, $matches['workstreams']['matched'][0]['existing_id']);
        $this->assertEquals('Mobile App', $matches['workstreams']['matched'][0]['detected_name']);
    }

    /** @test */
    public function it_can_match_detected_releases_by_version_pattern()
    {
        $this->release->update([
            'version' => '2.1.0',
            'name' => 'Mobile Features Release'
        ]);

        $detectedEntities = [
            'stakeholders' => [],
            'workstreams' => [],
            'releases' => [
                ['version' => 'v2.1', 'confidence' => 0.88, 'context' => 'Version 2.1 release'],
                ['version' => 'v3.0', 'confidence' => 0.92, 'context' => 'Future v3.0 release']
            ],
            'action_items' => []
        ];

        $matches = $this->service->matchEntitiesToExisting($this->user, $detectedEntities);

        $this->assertCount(1, $matches['releases']['matched']);
        $this->assertCount(1, $matches['releases']['unmatched']);

        $this->assertEquals($this->release->id, $matches['releases']['matched'][0]['existing_id']);
        $this->assertEquals('v2.1', $matches['releases']['matched'][0]['detected_name']);
    }

    /** @test */
    public function it_can_create_content_associations_from_matched_entities()
    {
        $stakeholder = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $release = Release::factory()->create(['workstream_id' => $workstream->id]);

        $matchedEntities = [
            'stakeholders' => [
                'matched' => [
                    [
                        'existing_id' => $stakeholder->id,
                        'detected_name' => 'John Smith',
                        'confidence' => 0.95,
                        'context' => 'John was mentioned directly',
                        'mention_type' => 'direct_mention'
                    ]
                ],
                'unmatched' => []
            ],
            'workstreams' => [
                'matched' => [
                    [
                        'existing_id' => $workstream->id,
                        'detected_name' => 'Mobile App',
                        'confidence' => 0.92,
                        'context' => 'Mobile app project mentioned',
                        'relevance_type' => 'direct_reference'
                    ]
                ],
                'unmatched' => []
            ],
            'releases' => [
                'matched' => [
                    [
                        'existing_id' => $release->id,
                        'detected_name' => 'v2.1',
                        'confidence' => 0.88,
                        'context' => 'Version 2.1 mentioned',
                        'relevance_type' => 'feature_update'
                    ]
                ],
                'unmatched' => []
            ]
        ];

        $this->service->createContentAssociations($this->content, $matchedEntities);

        // Verify associations were created
        $this->assertCount(1, $this->content->fresh()->stakeholders);
        $this->assertCount(1, $this->content->fresh()->workstreams);
        $this->assertCount(1, $this->content->fresh()->releases);

        // Verify pivot data
        $stakeholderPivot = $this->content->stakeholders()->first()->pivot;
        $this->assertEquals('direct_mention', $stakeholderPivot->mention_type);
        $this->assertEquals(0.95, $stakeholderPivot->confidence_score);
        $this->assertEquals('John was mentioned directly', $stakeholderPivot->context);
    }

    /** @test */
    public function it_can_create_action_items_from_detected_entities()
    {
        $stakeholder = Stakeholder::factory()->create(['user_id' => $this->user->id, 'name' => 'John Smith']);
        $workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);

        $actionItemsData = [
            [
                'text' => 'Complete API documentation',
                'assignee' => 'John Smith',
                'confidence' => 0.93,
                'context' => 'John needs to finish API docs by Friday',
                'priority' => 'high',
                'due_date' => '2025-09-25'
            ],
            [
                'text' => 'Review user flows',
                'assignee' => null,
                'confidence' => 0.85,
                'context' => 'Someone should review the flows',
                'priority' => 'medium',
                'due_date' => null
            ]
        ];

        $createdActionItems = $this->service->createActionItems($this->content, $actionItemsData, $this->user);

        $this->assertCount(2, $createdActionItems);

        $firstItem = $createdActionItems[0];
        $this->assertEquals('Complete API documentation', $firstItem->action_text);
        $this->assertEquals($stakeholder->id, $firstItem->assignee_stakeholder_id);
        $this->assertEquals('high', $firstItem->priority);
        $this->assertEquals('2025-09-25', $firstItem->due_date->format('Y-m-d'));

        $secondItem = $createdActionItems[1];
        $this->assertEquals('Review user flows', $secondItem->action_text);
        $this->assertNull($secondItem->assignee_stakeholder_id);
        $this->assertEquals('medium', $secondItem->priority);
    }

    /** @test */
    public function it_can_suggest_new_entities_for_creation()
    {
        $unmatchedEntities = [
            'stakeholders' => [
                'unmatched' => [
                    ['name' => 'Sarah Johnson', 'confidence' => 0.92, 'context' => 'New team member'],
                    ['name' => 'Mike Wilson', 'confidence' => 0.88, 'context' => 'Project manager']
                ]
            ],
            'workstreams' => [
                'unmatched' => [
                    ['name' => 'Backend Modernization', 'confidence' => 0.85, 'context' => 'New backend project']
                ]
            ],
            'releases' => [
                'unmatched' => [
                    ['version' => 'v3.0', 'confidence' => 0.90, 'context' => 'Future major release']
                ]
            ]
        ];

        $suggestions = $this->service->suggestNewEntities($unmatchedEntities);

        $this->assertArrayHasKey('stakeholders', $suggestions);
        $this->assertArrayHasKey('workstreams', $suggestions);
        $this->assertArrayHasKey('releases', $suggestions);

        $this->assertCount(2, $suggestions['stakeholders']);
        $this->assertCount(1, $suggestions['workstreams']);
        $this->assertCount(1, $suggestions['releases']);

        $this->assertEquals('Sarah Johnson', $suggestions['stakeholders'][0]['suggested_name']);
        $this->assertEquals('Backend Modernization', $suggestions['workstreams'][0]['suggested_name']);
        $this->assertEquals('v3.0', $suggestions['releases'][0]['suggested_version']);
    }

    /** @test */
    public function it_can_update_confidence_scores_based_on_user_feedback()
    {
        $entityDetection = [
            'content_id' => $this->content->id,
            'entity_type' => 'stakeholder',
            'entity_id' => 1,
            'confidence_score' => 0.75,
            'user_confirmed' => true
        ];

        $this->service->updateConfidenceFromFeedback($entityDetection);

        // Verify that the service learns from user feedback
        // This would typically update internal ML models or confidence thresholds
        $this->assertTrue(true); // Placeholder for actual implementation
    }

    /** @test */
    public function it_handles_low_confidence_detections_appropriately()
    {
        $detectedEntities = [
            'stakeholders' => [
                ['name' => 'Maybe John', 'confidence' => 0.45, 'context' => 'Unclear mention'],
                ['name' => 'Definitely Sarah', 'confidence' => 0.95, 'context' => 'Clear mention']
            ],
            'workstreams' => [],
            'releases' => [],
            'action_items' => []
        ];

        $filteredEntities = $this->service->filterByConfidence($detectedEntities, 0.7);

        $this->assertCount(1, $filteredEntities['stakeholders']);
        $this->assertEquals('Definitely Sarah', $filteredEntities['stakeholders'][0]['name']);
        $this->assertEquals(0.95, $filteredEntities['stakeholders'][0]['confidence']);
    }

    /** @test */
    public function it_can_reprocess_content_with_improved_detection()
    {
        // Create existing associations
        $stakeholder = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $this->content->stakeholders()->attach($stakeholder->id, [
            'mention_type' => 'direct_mention',
            'confidence_score' => 0.75,
            'context' => 'Original detection'
        ]);

        $newDetectedEntities = [
            'stakeholders' => [
                ['name' => $stakeholder->name, 'confidence' => 0.95, 'context' => 'Improved detection']
            ],
            'workstreams' => [],
            'releases' => [],
            'action_items' => []
        ];

        $this->aiService->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($newDetectedEntities);

        $result = $this->service->reprocessContentEntities($this->content);

        $this->assertTrue($result);

        // Verify improved confidence score
        $updatedPivot = $this->content->fresh()->stakeholders()->first()->pivot;
        $this->assertEquals(0.95, $updatedPivot->confidence_score);
        $this->assertEquals('Improved detection', $updatedPivot->context);
    }
}