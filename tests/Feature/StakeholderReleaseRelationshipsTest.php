<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Stakeholder;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StakeholderReleaseRelationshipsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users that will be stakeholders
        $this->productManager = User::factory()->create(['email' => 'pm@example.com']);
        $this->developer = User::factory()->create(['email' => 'dev@example.com']);
        $this->designer = User::factory()->create(['email' => 'design@example.com']);
        $this->legalTeam = User::factory()->create(['email' => 'legal@example.com']);

        // Create a test workstream
        $this->workstream = Workstream::factory()->create([
            'name' => 'Mobile App V2',
            'owner_id' => $this->productManager->id
        ]);

        // Create a test release
        $this->release = Release::factory()->create([
            'name' => 'Mobile App V2.1',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addDays(30)
        ]);
    }

    /** @test */
    public function pm_can_associate_stakeholders_with_releases()
    {
        // Given: A PM wants to assign stakeholders to a release
        $this->actingAs($this->productManager);

        // When: They assign stakeholders with different roles
        $stakeholderData = [
            [
                'user_id' => $this->developer->id,
                'role' => 'owner',
                'notification_preference' => 'email'
            ],
            [
                'user_id' => $this->designer->id,
                'role' => 'reviewer',
                'notification_preference' => 'slack'
            ],
            [
                'user_id' => $this->legalTeam->id,
                'role' => 'approver',
                'notification_preference' => 'email'
            ]
        ];

        $response = $this->postJson("/api/releases/{$this->release->id}/stakeholders", [
            'stakeholders' => $stakeholderData
        ]);

        // Then: The stakeholders should be successfully associated
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'release_id',
                    'role',
                    'notification_preference',
                    'created_at',
                    'user' => [
                        'id',
                        'name',
                        'email'
                    ]
                ]
            ]
        ]);

        // And: The database should contain the relationships
        $this->assertDatabaseHas('stakeholder_releases', [
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        $this->assertDatabaseHas('stakeholder_releases', [
            'release_id' => $this->release->id,
            'user_id' => $this->designer->id,
            'role' => 'reviewer',
            'notification_preference' => 'slack'
        ]);

        $this->assertDatabaseHas('stakeholder_releases', [
            'release_id' => $this->release->id,
            'user_id' => $this->legalTeam->id,
            'role' => 'approver',
            'notification_preference' => 'email'
        ]);
    }

    /** @test */
    public function stakeholder_roles_are_validated_correctly()
    {
        // Given: A PM trying to assign invalid stakeholder roles
        $this->actingAs($this->productManager);

        // When: They try to assign an invalid role
        $response = $this->postJson("/api/releases/{$this->release->id}/stakeholders", [
            'stakeholders' => [
                [
                    'user_id' => $this->developer->id,
                    'role' => 'invalid_role',
                    'notification_preference' => 'email'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('stakeholders.0.role');

        // And: Valid roles should be accepted
        $validRoles = ['owner', 'reviewer', 'approver', 'observer'];
        foreach ($validRoles as $index => $role) {
            // Create a new user for each role test to avoid unique constraint violation
            $testUser = User::factory()->create();

            $response = $this->postJson("/api/releases/{$this->release->id}/stakeholders", [
                'stakeholders' => [
                    [
                        'user_id' => $testUser->id,
                        'role' => $role,
                        'notification_preference' => 'email'
                    ]
                ]
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function notification_preferences_are_validated_correctly()
    {
        // Given: A PM trying to set notification preferences
        $this->actingAs($this->productManager);

        // When: They try to set an invalid notification preference
        $response = $this->postJson("/api/releases/{$this->release->id}/stakeholders", [
            'stakeholders' => [
                [
                    'user_id' => $this->developer->id,
                    'role' => 'owner',
                    'notification_preference' => 'invalid_preference'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('stakeholders.0.notification_preference');

        // And: Valid preferences should be accepted
        $validPreferences = ['email', 'slack', 'none'];
        foreach ($validPreferences as $preference) {
            // Clear previous stakeholders for this test
            StakeholderRelease::where('release_id', $this->release->id)->delete();

            $response = $this->postJson("/api/releases/{$this->release->id}/stakeholders", [
                'stakeholders' => [
                    [
                        'user_id' => $this->developer->id,
                        'role' => 'owner',
                        'notification_preference' => $preference
                    ]
                ]
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function pm_can_query_releases_by_stakeholder()
    {
        // Given: Multiple releases with different stakeholders
        $release2 = Release::factory()->create([
            'name' => 'Mobile App V2.2',
            'workstream_id' => $this->workstream->id
        ]);

        $release3 = Release::factory()->create([
            'name' => 'Web App V1.5',
            'workstream_id' => $this->workstream->id
        ]);

        // Associate stakeholders
        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        StakeholderRelease::create([
            'release_id' => $release2->id,
            'user_id' => $this->developer->id,
            'role' => 'reviewer',
            'notification_preference' => 'slack'
        ]);

        StakeholderRelease::create([
            'release_id' => $release3->id,
            'user_id' => $this->designer->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        // When: PM queries releases by stakeholder
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/stakeholders/{$this->developer->id}/releases");

        // Then: Only releases where the developer is a stakeholder should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $releaseIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->release->id, $releaseIds);
        $this->assertContains($release2->id, $releaseIds);
        $this->assertNotContains($release3->id, $releaseIds);
    }

    /** @test */
    public function pm_can_query_stakeholders_by_release()
    {
        // Given: A release with multiple stakeholders
        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->designer->id,
            'role' => 'reviewer',
            'notification_preference' => 'slack'
        ]);

        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->legalTeam->id,
            'role' => 'approver',
            'notification_preference' => 'email'
        ]);

        // When: PM queries stakeholders for this release
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/releases/{$this->release->id}/stakeholders");

        // Then: All stakeholders should be returned with their roles
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');

        $stakeholders = collect($response->json('data'));

        // Verify the developer stakeholder
        $developerStakeholder = $stakeholders->where('user.id', $this->developer->id)->first();
        $this->assertEquals('owner', $developerStakeholder['role']);
        $this->assertEquals('email', $developerStakeholder['notification_preference']);

        // Verify the designer stakeholder
        $designerStakeholder = $stakeholders->where('user.id', $this->designer->id)->first();
        $this->assertEquals('reviewer', $designerStakeholder['role']);
        $this->assertEquals('slack', $designerStakeholder['notification_preference']);

        // Verify the legal stakeholder
        $legalStakeholder = $stakeholders->where('user.id', $this->legalTeam->id)->first();
        $this->assertEquals('approver', $legalStakeholder['role']);
        $this->assertEquals('email', $legalStakeholder['notification_preference']);
    }

    /** @test */
    public function pm_can_filter_stakeholders_by_role()
    {
        // Given: A release with stakeholders in different roles
        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->designer->id,
            'role' => 'reviewer',
            'notification_preference' => 'slack'
        ]);

        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->legalTeam->id,
            'role' => 'approver',
            'notification_preference' => 'email'
        ]);

        // When: PM filters stakeholders by role
        $this->actingAs($this->productManager);

        // Then: Should return only approvers
        $response = $this->getJson("/api/releases/{$this->release->id}/stakeholders?role=approver");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($this->legalTeam->id, $response->json('data.0.user.id'));

        // And: Should return only reviewers
        $response = $this->getJson("/api/releases/{$this->release->id}/stakeholders?role=reviewer");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($this->designer->id, $response->json('data.0.user.id'));

        // And: Should return only owners
        $response = $this->getJson("/api/releases/{$this->release->id}/stakeholders?role=owner");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($this->developer->id, $response->json('data.0.user.id'));
    }

    /** @test */
    public function pm_can_update_stakeholder_role_and_preferences()
    {
        // Given: An existing stakeholder relationship
        $stakeholderRelease = StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'reviewer',
            'notification_preference' => 'email'
        ]);

        // When: PM updates the stakeholder's role and preferences
        $this->actingAs($this->productManager);
        $response = $this->putJson("/api/releases/{$this->release->id}/stakeholders/{$stakeholderRelease->id}", [
            'role' => 'owner',
            'notification_preference' => 'slack'
        ]);

        // Then: The stakeholder should be updated
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'role' => 'owner',
                'notification_preference' => 'slack'
            ]
        ]);

        // And: The database should reflect the changes
        $this->assertDatabaseHas('stakeholder_releases', [
            'id' => $stakeholderRelease->id,
            'role' => 'owner',
            'notification_preference' => 'slack'
        ]);
    }

    /** @test */
    public function pm_can_remove_stakeholder_from_release()
    {
        // Given: An existing stakeholder relationship
        $stakeholderRelease = StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'reviewer',
            'notification_preference' => 'email'
        ]);

        // When: PM removes the stakeholder from the release
        $this->actingAs($this->productManager);
        $response = $this->deleteJson("/api/releases/{$this->release->id}/stakeholders/{$stakeholderRelease->id}");

        // Then: The stakeholder should be removed
        $response->assertStatus(204);

        // And: The database should not contain the relationship
        $this->assertDatabaseMissing('stakeholder_releases', [
            'id' => $stakeholderRelease->id
        ]);
    }

    /** @test */
    public function only_authorized_users_can_manage_stakeholder_relationships()
    {
        // Given: A non-authorized user
        $unauthorizedUser = User::factory()->create();

        // When: They try to add stakeholders to a release
        $this->actingAs($unauthorizedUser);
        $response = $this->postJson("/api/releases/{$this->release->id}/stakeholders", [
            'stakeholders' => [
                [
                    'user_id' => $this->developer->id,
                    'role' => 'owner',
                    'notification_preference' => 'email'
                ]
            ]
        ]);

        // Then: The request should be forbidden
        $response->assertStatus(403);

        // And: The database should not contain any new relationships
        $this->assertDatabaseMissing('stakeholder_releases', [
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id
        ]);
    }

    /** @test */
    public function duplicate_stakeholder_assignments_are_prevented()
    {
        // Given: An existing stakeholder relationship
        StakeholderRelease::create([
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        // When: PM tries to add the same stakeholder again
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/releases/{$this->release->id}/stakeholders", [
            'stakeholders' => [
                [
                    'user_id' => $this->developer->id,
                    'role' => 'reviewer',
                    'notification_preference' => 'slack'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('stakeholders.0.user_id');

        // And: The original relationship should remain unchanged
        $this->assertDatabaseHas('stakeholder_releases', [
            'release_id' => $this->release->id,
            'user_id' => $this->developer->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);
    }
}