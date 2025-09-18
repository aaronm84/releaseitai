<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ReleaseDependency;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReleaseDependenciesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->productManager = User::factory()->create(['email' => 'pm@example.com']);
        $this->techLead = User::factory()->create(['email' => 'tech@example.com']);
        $this->platformOwner = User::factory()->create(['email' => 'platform@example.com']);

        // Create test workstreams
        $this->mobileWorkstream = Workstream::factory()->create([
            'name' => 'Mobile App',
            'owner_id' => $this->productManager->id
        ]);

        $this->backendWorkstream = Workstream::factory()->create([
            'name' => 'Backend Platform',
            'owner_id' => $this->platformOwner->id
        ]);

        $this->webWorkstream = Workstream::factory()->create([
            'name' => 'Web Application',
            'owner_id' => $this->techLead->id
        ]);

        // Create test releases
        $this->mobileRelease = Release::factory()->create([
            'name' => 'Mobile App V2.1',
            'workstream_id' => $this->mobileWorkstream->id,
            'target_date' => now()->addDays(30),
            'status' => 'planned'
        ]);

        $this->backendRelease = Release::factory()->create([
            'name' => 'API V3.0',
            'workstream_id' => $this->backendWorkstream->id,
            'target_date' => now()->addDays(20),
            'status' => 'in_progress'
        ]);

        $this->webRelease = Release::factory()->create([
            'name' => 'Web App V1.5',
            'workstream_id' => $this->webWorkstream->id,
            'target_date' => now()->addDays(35),
            'status' => 'planned'
        ]);
    }

    /** @test */
    public function pm_can_create_upstream_downstream_release_relationships()
    {
        // Given: A PM wants to create release dependencies
        $this->actingAs($this->productManager);

        // When: They create dependencies between releases
        $dependencies = [
            [
                'upstream_release_id' => $this->backendRelease->id,
                'downstream_release_id' => $this->mobileRelease->id,
                'dependency_type' => 'blocks',
                'description' => 'Mobile app requires new API endpoints'
            ],
            [
                'upstream_release_id' => $this->backendRelease->id,
                'downstream_release_id' => $this->webRelease->id,
                'dependency_type' => 'enables',
                'description' => 'Web app will leverage new API features'
            ]
        ];

        $response = $this->postJson("/api/release-dependencies", [
            'dependencies' => $dependencies
        ]);

        // Then: The dependencies should be created successfully
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'upstream_release_id',
                    'downstream_release_id',
                    'dependency_type',
                    'description',
                    'created_at',
                    'upstream_release' => [
                        'id',
                        'name',
                        'target_date',
                        'status'
                    ],
                    'downstream_release' => [
                        'id',
                        'name',
                        'target_date',
                        'status'
                    ]
                ]
            ]
        ]);

        // And: The database should contain the dependencies
        foreach ($dependencies as $dependency) {
            $this->assertDatabaseHas('release_dependencies', [
                'upstream_release_id' => $dependency['upstream_release_id'],
                'downstream_release_id' => $dependency['downstream_release_id'],
                'dependency_type' => $dependency['dependency_type'],
                'description' => $dependency['description']
            ]);
        }
    }

    /** @test */
    public function dependency_types_are_validated_correctly()
    {
        // Given: A PM trying to create dependencies
        $this->actingAs($this->productManager);

        // When: They try to create a dependency with invalid type
        $response = $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $this->backendRelease->id,
                    'downstream_release_id' => $this->mobileRelease->id,
                    'dependency_type' => 'invalid_type',
                    'description' => 'Test dependency'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('dependencies.0.dependency_type');

        // And: Valid dependency types should be accepted
        $validTypes = ['blocks', 'enables', 'informs'];
        foreach ($validTypes as $type) {
            // Clear previous dependencies
            ReleaseDependency::truncate();

            $response = $this->postJson("/api/release-dependencies", [
                'dependencies' => [
                    [
                        'upstream_release_id' => $this->backendRelease->id,
                        'downstream_release_id' => $this->mobileRelease->id,
                        'dependency_type' => $type,
                        'description' => "Test {$type} dependency"
                    ]
                ]
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function circular_dependencies_between_releases_are_prevented()
    {
        // Given: An existing dependency A -> B
        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks',
            'description' => 'Backend blocks mobile'
        ]);

        // When: PM tries to create a circular dependency B -> A
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $this->mobileRelease->id,
                    'downstream_release_id' => $this->backendRelease->id,
                    'dependency_type' => 'blocks',
                    'description' => 'This would create a circular dependency'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('dependencies.0.downstream_release_id');
        $response->assertJson([
            'errors' => [
                'dependencies.0.downstream_release_id' => ['Creating this dependency would result in a circular dependency.']
            ]
        ]);

        // And: No circular dependency should exist in the database
        $this->assertDatabaseMissing('release_dependencies', [
            'upstream_release_id' => $this->mobileRelease->id,
            'downstream_release_id' => $this->backendRelease->id
        ]);
    }

    /** @test */
    public function complex_circular_dependencies_are_detected()
    {
        // Given: A chain of dependencies A -> B -> C
        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $this->mobileRelease->id,
            'downstream_release_id' => $this->webRelease->id,
            'dependency_type' => 'blocks'
        ]);

        // When: PM tries to create C -> A (completing the circle)
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $this->webRelease->id,
                    'downstream_release_id' => $this->backendRelease->id,
                    'dependency_type' => 'blocks',
                    'description' => 'This would create a complex circular dependency'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('dependencies.0.downstream_release_id');
    }

    /** @test */
    public function self_dependencies_are_prevented()
    {
        // Given: A PM trying to create a self-dependency
        $this->actingAs($this->productManager);

        // When: They try to make a release depend on itself
        $response = $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $this->mobileRelease->id,
                    'downstream_release_id' => $this->mobileRelease->id,
                    'dependency_type' => 'blocks',
                    'description' => 'Self dependency'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('dependencies.0.downstream_release_id');
        $response->assertJson([
            'errors' => [
                'dependencies.0.downstream_release_id' => ['A release cannot depend on itself.']
            ]
        ]);
    }

    /** @test */
    public function impact_analysis_identifies_affected_releases_when_upstream_is_delayed()
    {
        // Given: A chain of dependencies
        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->webRelease->id,
            'dependency_type' => 'enables'
        ]);

        // When: The upstream release is delayed
        $this->actingAs($this->platformOwner);
        $newTargetDate = now()->addDays(45); // Delayed by 25 days

        $response = $this->putJson("/api/releases/{$this->backendRelease->id}", [
            'target_date' => $newTargetDate->toDateString(),
            'delay_reason' => 'Technical complexity higher than expected'
        ]);

        // Then: The release should be updated
        $response->assertStatus(200);

        // And: Impact analysis should be available
        $impactResponse = $this->getJson("/api/releases/{$this->backendRelease->id}/impact-analysis");
        $impactResponse->assertStatus(200);
        $impactResponse->assertJsonStructure([
            'data' => [
                'delayed_release' => [
                    'id',
                    'name',
                    'original_target_date',
                    'new_target_date',
                    'delay_days'
                ],
                'affected_releases' => [
                    '*' => [
                        'id',
                        'name',
                        'dependency_type',
                        'current_target_date',
                        'recommended_new_date',
                        'impact_severity', // 'high', 'medium', 'low'
                        'workstream' => [
                            'id',
                            'name',
                            'owner'
                        ]
                    ]
                ],
                'total_affected_releases',
                'critical_path_releases'
            ]
        ]);

        // And: Both downstream releases should be identified as affected
        $affectedReleases = $impactResponse->json('data.affected_releases');
        $this->assertCount(2, $affectedReleases);

        $affectedIds = collect($affectedReleases)->pluck('id')->toArray();
        $this->assertContains($this->mobileRelease->id, $affectedIds);
        $this->assertContains($this->webRelease->id, $affectedIds);
    }

    /** @test */
    public function blocking_dependencies_have_higher_impact_severity_than_enabling()
    {
        // Given: Both blocking and enabling dependencies
        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->webRelease->id,
            'dependency_type' => 'enables'
        ]);

        // When: The upstream release is delayed
        $this->actingAs($this->platformOwner);
        $this->putJson("/api/releases/{$this->backendRelease->id}", [
            'target_date' => now()->addDays(45)->toDateString()
        ]);

        // Then: Impact analysis should show different severities
        $response = $this->getJson("/api/releases/{$this->backendRelease->id}/impact-analysis");
        $affectedReleases = collect($response->json('data.affected_releases'));

        $mobileImpact = $affectedReleases->where('id', $this->mobileRelease->id)->first();
        $webImpact = $affectedReleases->where('id', $this->webRelease->id)->first();

        // Blocking dependency should have higher impact
        $this->assertEquals('high', $mobileImpact['impact_severity']);
        $this->assertEquals('medium', $webImpact['impact_severity']);
    }

    /** @test */
    public function pm_can_query_all_dependencies_for_a_release()
    {
        // Given: A release with both upstream and downstream dependencies
        $additionalUpstreamRelease = Release::factory()->create([
            'name' => 'Design System V2',
            'workstream_id' => $this->webWorkstream->id,
            'target_date' => now()->addDays(10)
        ]);

        $additionalDownstreamRelease = Release::factory()->create([
            'name' => 'Mobile App V2.2',
            'workstream_id' => $this->mobileWorkstream->id,
            'target_date' => now()->addDays(60)
        ]);

        // Create upstream dependencies (things this release depends on)
        ReleaseDependency::create([
            'upstream_release_id' => $additionalUpstreamRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'enables'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks'
        ]);

        // Create downstream dependencies (things that depend on this release)
        ReleaseDependency::create([
            'upstream_release_id' => $this->mobileRelease->id,
            'downstream_release_id' => $additionalDownstreamRelease->id,
            'dependency_type' => 'blocks'
        ]);

        // When: PM queries all dependencies for the mobile release
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/releases/{$this->mobileRelease->id}/dependencies");

        // Then: Both upstream and downstream dependencies should be returned
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'upstream_dependencies' => [
                    '*' => [
                        'id',
                        'upstream_release',
                        'dependency_type',
                        'description'
                    ]
                ],
                'downstream_dependencies' => [
                    '*' => [
                        'id',
                        'downstream_release',
                        'dependency_type',
                        'description'
                    ]
                ]
            ]
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['upstream_dependencies']);
        $this->assertCount(1, $data['downstream_dependencies']);
    }

    /** @test */
    public function pm_can_update_dependency_details()
    {
        // Given: An existing dependency
        $dependency = ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks',
            'description' => 'Original description'
        ]);

        // When: PM updates the dependency
        $this->actingAs($this->productManager);
        $response = $this->putJson("/api/release-dependencies/{$dependency->id}", [
            'dependency_type' => 'enables',
            'description' => 'Updated description with more details'
        ]);

        // Then: The dependency should be updated
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'dependency_type' => 'enables',
                'description' => 'Updated description with more details'
            ]
        ]);

        // And: The database should reflect the changes
        $this->assertDatabaseHas('release_dependencies', [
            'id' => $dependency->id,
            'dependency_type' => 'enables',
            'description' => 'Updated description with more details'
        ]);
    }

    /** @test */
    public function pm_can_remove_release_dependencies()
    {
        // Given: An existing dependency
        $dependency = ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks',
            'description' => 'To be removed'
        ]);

        // When: PM removes the dependency
        $this->actingAs($this->productManager);
        $response = $this->deleteJson("/api/release-dependencies/{$dependency->id}");

        // Then: The dependency should be removed
        $response->assertStatus(204);

        // And: The database should not contain the dependency
        $this->assertDatabaseMissing('release_dependencies', [
            'id' => $dependency->id
        ]);
    }

    /** @test */
    public function only_authorized_users_can_manage_release_dependencies()
    {
        // Given: An unauthorized user
        $unauthorizedUser = User::factory()->create();

        // When: They try to create dependencies
        $this->actingAs($unauthorizedUser);
        $response = $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $this->backendRelease->id,
                    'downstream_release_id' => $this->mobileRelease->id,
                    'dependency_type' => 'blocks',
                    'description' => 'Unauthorized dependency'
                ]
            ]
        ]);

        // Then: The request should be forbidden
        $response->assertStatus(403);

        // And: No dependency should be created
        $this->assertDatabaseMissing('release_dependencies', [
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id
        ]);
    }

    /** @test */
    public function duplicate_dependencies_are_prevented()
    {
        // Given: An existing dependency
        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks',
            'description' => 'Original dependency'
        ]);

        // When: PM tries to create the same dependency again
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $this->backendRelease->id,
                    'downstream_release_id' => $this->mobileRelease->id,
                    'dependency_type' => 'enables', // Different type, but same releases
                    'description' => 'Duplicate dependency'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('dependencies.0.downstream_release_id');

        // And: Only the original dependency should exist
        $this->assertEquals(1, ReleaseDependency::where([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id
        ])->count());
    }

    /** @test */
    public function critical_path_is_calculated_correctly()
    {
        // Given: A complex dependency chain
        $designSystemRelease = Release::factory()->create([
            'workstream_id' => $this->webWorkstream->id,
            'target_date' => now()->addDays(15)
        ]);

        // Chain: Design System -> Backend -> Mobile -> Future Release
        $futureRelease = Release::factory()->create([
            'workstream_id' => $this->mobileWorkstream->id,
            'target_date' => now()->addDays(50)
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $designSystemRelease->id,
            'downstream_release_id' => $this->backendRelease->id,
            'dependency_type' => 'blocks'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->mobileRelease->id,
            'dependency_type' => 'blocks'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $this->mobileRelease->id,
            'downstream_release_id' => $futureRelease->id,
            'dependency_type' => 'blocks'
        ]);

        // Also create a non-critical path
        ReleaseDependency::create([
            'upstream_release_id' => $this->backendRelease->id,
            'downstream_release_id' => $this->webRelease->id,
            'dependency_type' => 'enables'
        ]);

        // When: PM requests critical path analysis
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/workstreams/{$this->mobileWorkstream->id}/critical-path");

        // Then: The critical path should be identified
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'critical_path' => [
                    '*' => [
                        'release_id',
                        'release_name',
                        'target_date',
                        'dependencies_count',
                        'position_in_path'
                    ]
                ],
                'total_duration_days',
                'risk_level', // 'low', 'medium', 'high'
                'bottlenecks' => [
                    '*' => [
                        'release_id',
                        'issue_type',
                        'description'
                    ]
                ]
            ]
        ]);

        $criticalPath = $response->json('data.critical_path');
        $criticalPathIds = collect($criticalPath)->pluck('release_id')->toArray();

        // The main blocking chain should be in the critical path
        $this->assertContains($designSystemRelease->id, $criticalPathIds);
        $this->assertContains($this->backendRelease->id, $criticalPathIds);
        $this->assertContains($this->mobileRelease->id, $criticalPathIds);
        $this->assertContains($futureRelease->id, $criticalPathIds);

        // The enabling dependency should not be critical
        $this->assertNotContains($this->webRelease->id, $criticalPathIds);
    }
}