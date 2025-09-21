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
 * Focused tests for ContentProcessor entity matching logic
 * These tests specifically validate the intelligent entity matching algorithms
 */
class ContentProcessorEntityMatchingTest extends TestCase
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
     * Test Case: Stakeholder Matching - Multiple Exact Name Matches
     *
     * Given: Multiple existing stakeholders with the same name
     * When: ContentProcessor matches extracted stakeholder with that name
     * Then: It should return all potential matches for disambiguation
     */
    public function test_returns_multiple_exact_name_matches_for_disambiguation()
    {
        $stakeholder1 = Stakeholder::factory()->create([
            'name' => 'John Smith',
            'email' => 'john.smith@companyA.com',
            'company' => 'Company A',
            'user_id' => $this->user->id
        ]);

        $stakeholder2 = Stakeholder::factory()->create([
            'name' => 'John Smith',
            'email' => 'john.smith@companyB.com',
            'company' => 'Company B',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'John Smith', 'confidence' => 0.95, 'context' => 'mentioned in meeting']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $this->assertCount(2, $result['stakeholders']['matches']);
        $this->assertEquals('exact_name', $result['stakeholders']['matches'][0]['match_type']);
        $this->assertEquals('exact_name', $result['stakeholders']['matches'][1]['match_type']);
        $this->assertTrue($result['stakeholders']['requires_disambiguation']);
    }

    /**
     * Test Case: Stakeholder Matching - Fuzzy Name with Company Context
     *
     * Given: Extracted stakeholder with partial name and company context
     * When: ContentProcessor performs fuzzy matching
     * Then: It should match using both name similarity and company information
     */
    public function test_matches_stakeholder_using_name_and_company_context()
    {
        $stakeholder = Stakeholder::factory()->create([
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@techcorp.com',
            'company' => 'TechCorp',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'S. Johnson',
                    'company' => 'TechCorp',
                    'confidence' => 0.80,
                    'context' => 'from TechCorp team'
                ]
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $this->assertCount(1, $result['stakeholders']['matches']);
        $match = $result['stakeholders']['matches'][0];
        $this->assertEquals($stakeholder->id, $match['existing_entity']->id);
        $this->assertEquals('fuzzy_name_with_context', $match['match_type']);
        $this->assertGreaterThan(0.85, $match['match_confidence']);
    }

    /**
     * Test Case: Stakeholder Matching - Handle Name Variations
     *
     * Given: Stakeholder with common name variations (nicknames, shortened forms)
     * When: ContentProcessor processes different name formats
     * Then: It should recognize common name variations and match appropriately
     */
    public function test_matches_common_name_variations()
    {
        $stakeholder = Stakeholder::factory()->create([
            'name' => 'Robert Johnson',
            'email' => 'bob.johnson@company.com',
            'user_id' => $this->user->id
        ]);

        $nameVariations = [
            ['name' => 'Bob Johnson', 'expected_confidence' => 0.90],
            ['name' => 'Rob Johnson', 'expected_confidence' => 0.85],
            ['name' => 'Bobby Johnson', 'expected_confidence' => 0.80],
            ['name' => 'R. Johnson', 'expected_confidence' => 0.75]
        ];

        foreach ($nameVariations as $variation) {
            $extractedEntities = [
                'stakeholders' => [
                    [
                        'name' => $variation['name'],
                        'confidence' => 0.90,
                        'context' => 'mentioned in discussion'
                    ]
                ]
            ];

            $result = $this->contentProcessor->matchEntities($extractedEntities);

            $this->assertCount(1, $result['stakeholders']['matches']);
            $match = $result['stakeholders']['matches'][0];
            $this->assertEquals($stakeholder->id, $match['existing_entity']->id);
            $this->assertGreaterThanOrEqual($variation['expected_confidence'], $match['match_confidence']);
        }
    }

    /**
     * Test Case: Workstream Matching - Hierarchical Context
     *
     * Given: Parent and child workstreams with similar names
     * When: ContentProcessor matches extracted workstream
     * Then: It should consider hierarchical context for better matching
     */
    public function test_matches_workstream_with_hierarchical_context()
    {
        $parentWorkstream = Workstream::factory()->create([
            'name' => 'Mobile Platform',
            'type' => 'product_line',
            'owner_id' => $this->user->id
        ]);

        $childWorkstream = Workstream::factory()->create([
            'name' => 'Mobile Authentication',
            'type' => 'initiative',
            'parent_workstream_id' => $parentWorkstream->id,
            'owner_id' => $this->user->id
        ]);

        $extractedEntities = [
            'workstreams' => [
                [
                    'name' => 'Mobile Auth',
                    'confidence' => 0.85,
                    'context' => 'sub-component of mobile platform'
                ]
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $this->assertCount(1, $result['workstreams']['matches']);
        $match = $result['workstreams']['matches'][0];
        $this->assertEquals($childWorkstream->id, $match['existing_entity']->id);
        $this->assertEquals('fuzzy_name_with_hierarchy', $match['match_type']);
    }

    /**
     * Test Case: Release Matching - Version Pattern Recognition
     *
     * Given: Releases with various version formats
     * When: ContentProcessor extracts release information
     * Then: It should recognize and match different version patterns
     */
    public function test_matches_release_versions_with_pattern_recognition()
    {
        $workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);

        $releases = [
            Release::factory()->create([
                'name' => 'Q1 2024 Release',
                'version' => '2.1.0',
                'workstream_id' => $workstream->id
            ]),
            Release::factory()->create([
                'name' => 'Hotfix Release',
                'version' => '2.1.1',
                'workstream_id' => $workstream->id
            ]),
            Release::factory()->create([
                'name' => 'Beta Release',
                'version' => '2.2.0-beta',
                'workstream_id' => $workstream->id
            ])
        ];

        $versionPatterns = [
            ['version' => 'v2.1.0', 'expected_match' => $releases[0]],
            ['version' => '2.1.1-hotfix', 'expected_match' => $releases[1]],
            ['version' => '2.2 beta', 'expected_match' => $releases[2]]
        ];

        foreach ($versionPatterns as $pattern) {
            $extractedEntities = [
                'releases' => [
                    [
                        'version' => $pattern['version'],
                        'confidence' => 0.90,
                        'context' => 'version mentioned'
                    ]
                ]
            ];

            $result = $this->contentProcessor->matchEntities($extractedEntities);

            $this->assertCount(1, $result['releases']['matches']);
            $match = $result['releases']['matches'][0];
            $this->assertEquals($pattern['expected_match']->id, $match['existing_entity']->id);
            $this->assertEquals('version_pattern', $match['match_type']);
        }
    }

    /**
     * Test Case: Cross-Entity Matching - Workstream-Release Relationships
     *
     * Given: Extracted entities that reference related workstreams and releases
     * When: ContentProcessor performs entity matching
     * Then: It should use cross-entity relationships to improve match confidence
     */
    public function test_uses_cross_entity_relationships_for_matching()
    {
        $workstream = Workstream::factory()->create([
            'name' => 'Payment System',
            'owner_id' => $this->user->id
        ]);

        $release = Release::factory()->create([
            'name' => 'Payment v2.0',
            'version' => '2.0.0',
            'workstream_id' => $workstream->id
        ]);

        $extractedEntities = [
            'workstreams' => [
                ['name' => 'Payment', 'confidence' => 0.75, 'context' => 'system mentioned']
            ],
            'releases' => [
                ['version' => 'v2.0', 'confidence' => 0.80, 'context' => 'payment system release']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        // Workstream match confidence should be boosted by related release
        $workstreamMatch = $result['workstreams']['matches'][0];
        $this->assertEquals($workstream->id, $workstreamMatch['existing_entity']->id);
        $this->assertGreaterThan(0.85, $workstreamMatch['match_confidence']);

        // Release match confidence should be boosted by related workstream
        $releaseMatch = $result['releases']['matches'][0];
        $this->assertEquals($release->id, $releaseMatch['existing_entity']->id);
        $this->assertGreaterThan(0.90, $releaseMatch['match_confidence']);
    }

    /**
     * Test Case: Matching Threshold Configuration
     *
     * Given: Different confidence thresholds for entity matching
     * When: ContentProcessor applies matching logic
     * Then: It should respect configured thresholds for different match types
     */
    public function test_respects_configured_matching_thresholds()
    {
        $stakeholder = Stakeholder::factory()->create([
            'name' => 'John Smith',
            'user_id' => $this->user->id
        ]);

        // Test with different similarity scores
        $testCases = [
            ['name' => 'J Smith', 'expected_match' => true, 'threshold' => 0.70],
            ['name' => 'John S', 'expected_match' => true, 'threshold' => 0.70],
            ['name' => 'Jon Smith', 'expected_match' => true, 'threshold' => 0.70],
            ['name' => 'Jane Smith', 'expected_match' => false, 'threshold' => 0.70],
            ['name' => 'John Doe', 'expected_match' => false, 'threshold' => 0.70]
        ];

        foreach ($testCases as $testCase) {
            $extractedEntities = [
                'stakeholders' => [
                    [
                        'name' => $testCase['name'],
                        'confidence' => 0.90,
                        'context' => 'test matching'
                    ]
                ]
            ];

            $result = $this->contentProcessor->matchEntities($extractedEntities, [
                'fuzzy_match_threshold' => $testCase['threshold']
            ]);

            if ($testCase['expected_match']) {
                $this->assertGreaterThan(0, count($result['stakeholders']['matches']));
            } else {
                $this->assertEquals(0, count($result['stakeholders']['matches']));
            }
        }
    }

    /**
     * Test Case: Entity Matching with Multi-User Isolation
     *
     * Given: Different users with entities having similar names
     * When: ContentProcessor performs entity matching
     * Then: It should only match entities belonging to the current user
     */
    public function test_isolates_entity_matching_by_user()
    {
        $otherUser = User::factory()->create();

        // Create stakeholder for other user
        $otherUserStakeholder = Stakeholder::factory()->create([
            'name' => 'John Smith',
            'email' => 'john@othercompany.com',
            'user_id' => $otherUser->id
        ]);

        // Create stakeholder for current user
        $currentUserStakeholder = Stakeholder::factory()->create([
            'name' => 'John Smith',
            'email' => 'john@mycompany.com',
            'user_id' => $this->user->id
        ]);

        $extractedEntities = [
            'stakeholders' => [
                ['name' => 'John Smith', 'confidence' => 0.95, 'context' => 'mentioned in meeting']
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $this->assertCount(1, $result['stakeholders']['matches']);
        $match = $result['stakeholders']['matches'][0];
        $this->assertEquals($currentUserStakeholder->id, $match['existing_entity']->id);
        $this->assertNotEquals($otherUserStakeholder->id, $match['existing_entity']->id);
    }

    /**
     * Test Case: Partial Data Matching - Email Domain Analysis
     *
     * Given: Extracted stakeholder with only email domain information
     * When: ContentProcessor performs matching
     * Then: It should suggest potential matches from the same organization
     */
    public function test_suggests_matches_based_on_email_domain()
    {
        $existingStakeholders = Stakeholder::factory()->count(3)->create([
            'company' => 'TechCorp',
            'user_id' => $this->user->id
        ]);

        $existingStakeholders[0]->update(['email' => 'alice@techcorp.com']);
        $existingStakeholders[1]->update(['email' => 'bob@techcorp.com']);
        $existingStakeholders[2]->update(['email' => 'charlie@othercorp.com']);

        $extractedEntities = [
            'stakeholders' => [
                [
                    'name' => 'New Person',
                    'email' => 'newperson@techcorp.com',
                    'confidence' => 0.90,
                    'context' => 'email from TechCorp domain'
                ]
            ]
        ];

        $result = $this->contentProcessor->matchEntities($extractedEntities);

        $this->assertCount(0, $result['stakeholders']['matches']); // No direct match
        $this->assertCount(1, $result['stakeholders']['suggestions']);

        $suggestion = $result['stakeholders']['suggestions'][0];
        $this->assertEquals('create_new_with_company_context', $suggestion['action']);
        $this->assertEquals('TechCorp', $suggestion['suggested_company']);
        $this->assertCount(2, $suggestion['related_stakeholders']); // Alice and Bob from same domain
    }

    /**
     * Test Case: Confidence Score Calculation Accuracy
     *
     * Given: Various entity matching scenarios
     * When: ContentProcessor calculates match confidence scores
     * Then: Confidence scores should accurately reflect match quality
     */
    public function test_calculates_accurate_confidence_scores()
    {
        $stakeholder = Stakeholder::factory()->create([
            'name' => 'Sarah Elizabeth Johnson',
            'email' => 'sarah.johnson@company.com',
            'phone' => '+1-555-123-4567',
            'title' => 'Senior Engineer',
            'user_id' => $this->user->id
        ]);

        $matchingScenarios = [
            [
                'extracted' => [
                    'name' => 'Sarah Elizabeth Johnson',
                    'email' => 'sarah.johnson@company.com',
                    'phone' => '+1-555-123-4567'
                ],
                'expected_confidence' => 0.99, // Nearly perfect match
                'match_type' => 'exact_multiple_fields'
            ],
            [
                'extracted' => [
                    'name' => 'Sarah Johnson',
                    'email' => 'sarah.johnson@company.com'
                ],
                'expected_confidence' => 0.95, // Exact email + partial name
                'match_type' => 'email_with_name'
            ],
            [
                'extracted' => [
                    'name' => 'S. Johnson',
                    'title' => 'Senior Engineer'
                ],
                'expected_confidence' => 0.80, // Partial name + exact title
                'match_type' => 'fuzzy_name_with_context'
            ],
            [
                'extracted' => [
                    'name' => 'Sarah J'
                ],
                'expected_confidence' => 0.70, // Partial name only
                'match_type' => 'fuzzy_name'
            ]
        ];

        foreach ($matchingScenarios as $scenario) {
            $extractedEntities = [
                'stakeholders' => [
                    array_merge($scenario['extracted'], [
                        'confidence' => 0.90,
                        'context' => 'test scenario'
                    ])
                ]
            ];

            $result = $this->contentProcessor->matchEntities($extractedEntities);

            $this->assertGreaterThan(0, count($result['stakeholders']['matches']));
            $match = $result['stakeholders']['matches'][0];

            $this->assertEquals($stakeholder->id, $match['existing_entity']->id);
            $this->assertEquals($scenario['match_type'], $match['match_type']);
            $this->assertEqualsWithDelta(
                $scenario['expected_confidence'],
                $match['match_confidence'],
                0.05, // Allow 5% variance
                "Confidence score mismatch for scenario: {$scenario['match_type']}"
            );
        }
    }
}