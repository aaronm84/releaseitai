<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Stakeholder;
use App\Models\Release;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test suite for Stakeholder Controller API endpoints.
 *
 * This test suite ensures that:
 * - CRUD operations respect tenant boundaries
 * - Filtering and searching work within tenant scope
 * - Proper authorization checks are enforced
 * - API responses follow expected format
 * - Validation rules are properly applied
 * - Pagination works correctly with tenant scoping
 */
class StakeholderControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $otherUser;
    private Stakeholder $stakeholder1;
    private Stakeholder $stakeholder2;
    private Stakeholder $otherUserStakeholder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'other@example.com',
            'name' => 'Other User'
        ]);

        $this->stakeholder1 = Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'title' => 'Senior Manager',
            'company' => 'ACME Corp',
            'influence_level' => 'high',
            'support_level' => 'medium',
        ]);

        $this->stakeholder2 = Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'title' => 'Director',
            'company' => 'Tech Solutions',
            'influence_level' => 'medium',
            'support_level' => 'high',
        ]);

        $this->otherUserStakeholder = Stakeholder::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'Other Stakeholder',
            'email' => 'other.stakeholder@example.com',
        ]);
    }

    /** @test */
    public function it_requires_authentication_to_access_stakeholders()
    {
        // When making a request without authentication
        $response = $this->getJson('/api/stakeholders');

        // Then it should return unauthorized
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /** @test */
    public function it_returns_only_current_users_stakeholders()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When requesting stakeholders
        $response = $this->getJson('/api/stakeholders');

        // Then only current user's stakeholders should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'John Doe'])
            ->assertJsonFragment(['name' => 'Jane Smith'])
            ->assertJsonMissing(['name' => 'Other Stakeholder']);
    }

    /** @test */
    public function it_can_create_a_new_stakeholder()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        $stakeholderData = [
            'name' => 'New Stakeholder',
            'email' => 'new.stakeholder@example.com',
            'title' => 'Project Manager',
            'company' => 'New Company',
            'department' => 'Operations',
            'phone' => '+1-555-0123',
            'preferred_communication_channel' => 'email',
            'communication_frequency' => 'weekly',
            'influence_level' => 'high',
            'support_level' => 'medium',
            'timezone' => 'America/New_York',
            'tags' => ['important', 'project-lead'],
            'stakeholder_notes' => 'Key contact for project X',
        ];

        // When creating a stakeholder
        $response = $this->postJson('/api/stakeholders', $stakeholderData);

        // Then the stakeholder should be created successfully
        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonFragment(['name' => 'New Stakeholder'])
            ->assertJsonFragment(['email' => 'new.stakeholder@example.com'])
            ->assertJsonFragment(['user_id' => $this->user->id]);

        $this->assertDatabaseHas('stakeholders', [
            'name' => 'New Stakeholder',
            'email' => 'new.stakeholder@example.com',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_stakeholder()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When creating a stakeholder without required fields
        $response = $this->postJson('/api/stakeholders', []);

        // Then validation errors should be returned
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    /** @test */
    public function it_validates_email_format_when_creating_stakeholder()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When creating a stakeholder with invalid email
        $response = $this->postJson('/api/stakeholders', [
            'name' => 'Test Stakeholder',
            'email' => 'invalid-email',
        ]);

        // Then validation error should be returned
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_validates_unique_email_per_tenant_when_creating_stakeholder()
    {
        // Given an authenticated user with an existing stakeholder
        Sanctum::actingAs($this->user);

        // When creating a stakeholder with duplicate email for same user
        $response = $this->postJson('/api/stakeholders', [
            'name' => 'Duplicate Email Stakeholder',
            'email' => $this->stakeholder1->email, // Same email as existing stakeholder
        ]);

        // Then validation error should be returned
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_allows_same_email_for_different_tenants_when_creating_stakeholder()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When creating a stakeholder with same email as another tenant's stakeholder
        $response = $this->postJson('/api/stakeholders', [
            'name' => 'Same Email Different Tenant',
            'email' => $this->otherUserStakeholder->email, // Same email but different tenant
        ]);

        // Then the stakeholder should be created successfully
        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('stakeholders', [
            'name' => 'Same Email Different Tenant',
            'email' => $this->otherUserStakeholder->email,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_show_a_specific_stakeholder()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When requesting a specific stakeholder
        $response = $this->getJson("/api/stakeholders/{$this->stakeholder1->id}");

        // Then the stakeholder details should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'John Doe'])
            ->assertJsonFragment(['email' => 'john.doe@example.com'])
            ->assertJsonFragment(['title' => 'Senior Manager'])
            ->assertJsonFragment(['company' => 'ACME Corp']);
    }

    /** @test */
    public function it_cannot_show_other_users_stakeholders()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When requesting another user's stakeholder
        $response = $this->getJson("/api/stakeholders/{$this->otherUserStakeholder->id}");

        // Then it should return not found
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @test */
    public function it_can_update_a_stakeholder()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        $updateData = [
            'name' => 'Updated John Doe',
            'title' => 'Senior Director',
            'company' => 'Updated ACME Corp',
            'influence_level' => 'high',
            'support_level' => 'high',
        ];

        // When updating a stakeholder
        $response = $this->putJson("/api/stakeholders/{$this->stakeholder1->id}", $updateData);

        // Then the stakeholder should be updated successfully
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['name' => 'Updated John Doe'])
            ->assertJsonFragment(['title' => 'Senior Director'])
            ->assertJsonFragment(['company' => 'Updated ACME Corp']);

        $this->assertDatabaseHas('stakeholders', [
            'id' => $this->stakeholder1->id,
            'name' => 'Updated John Doe',
            'title' => 'Senior Director',
            'company' => 'Updated ACME Corp',
        ]);
    }

    /** @test */
    public function it_cannot_update_other_users_stakeholders()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When trying to update another user's stakeholder
        $response = $this->putJson("/api/stakeholders/{$this->otherUserStakeholder->id}", [
            'name' => 'Unauthorized Update',
        ]);

        // Then it should return not found
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @test */
    public function it_can_delete_a_stakeholder()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When deleting a stakeholder
        $response = $this->deleteJson("/api/stakeholders/{$this->stakeholder1->id}");

        // Then the stakeholder should be deleted successfully
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('stakeholders', [
            'id' => $this->stakeholder1->id,
        ]);
    }

    /** @test */
    public function it_cannot_delete_other_users_stakeholders()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When trying to delete another user's stakeholder
        $response = $this->deleteJson("/api/stakeholders/{$this->otherUserStakeholder->id}");

        // Then it should return not found
        $response->assertStatus(Response::HTTP_NOT_FOUND);

        // And the stakeholder should still exist
        $this->assertDatabaseHas('stakeholders', [
            'id' => $this->otherUserStakeholder->id,
        ]);
    }

    /** @test */
    public function it_can_search_stakeholders_by_name()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When searching for stakeholders by name
        $response = $this->getJson('/api/stakeholders?search=John');

        // Then only matching stakeholders should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'John Doe'])
            ->assertJsonMissing(['name' => 'Jane Smith']);
    }

    /** @test */
    public function it_can_search_stakeholders_by_email()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When searching for stakeholders by email
        $response = $this->getJson('/api/stakeholders?search=jane.smith');

        // Then only matching stakeholders should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Jane Smith'])
            ->assertJsonMissing(['name' => 'John Doe']);
    }

    /** @test */
    public function it_can_filter_stakeholders_by_influence_level()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When filtering by influence level
        $response = $this->getJson('/api/stakeholders?influence_level=high');

        // Then only stakeholders with high influence should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'John Doe'])
            ->assertJsonMissing(['name' => 'Jane Smith']);
    }

    /** @test */
    public function it_can_filter_stakeholders_by_support_level()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When filtering by support level
        $response = $this->getJson('/api/stakeholders?support_level=high');

        // Then only stakeholders with high support should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Jane Smith'])
            ->assertJsonMissing(['name' => 'John Doe']);
    }

    /** @test */
    public function it_can_filter_stakeholders_by_company()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When filtering by company
        $response = $this->getJson('/api/stakeholders?company=ACME Corp');

        // Then only stakeholders from that company should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'John Doe'])
            ->assertJsonMissing(['name' => 'Jane Smith']);
    }

    /** @test */
    public function it_can_sort_stakeholders_by_name()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When sorting by name ascending
        $response = $this->getJson('/api/stakeholders?sort=name&direction=asc');

        // Then stakeholders should be returned in alphabetical order
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        $this->assertEquals('Jane Smith', $data[0]['name']);
        $this->assertEquals('John Doe', $data[1]['name']);
    }

    /** @test */
    public function it_can_sort_stakeholders_by_influence_level()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When sorting by influence level descending
        $response = $this->getJson('/api/stakeholders?sort=influence_level&direction=desc');

        // Then stakeholders should be returned with high influence first
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        $this->assertEquals('high', $data[0]['influence_level']);
        $this->assertEquals('medium', $data[1]['influence_level']);
    }

    /** @test */
    public function it_paginates_stakeholders_correctly()
    {
        // Given an authenticated user with many stakeholders
        Sanctum::actingAs($this->user);

        // Create additional stakeholders
        Stakeholder::factory()->count(20)->create(['user_id' => $this->user->id]);

        // When requesting paginated stakeholders
        $response = $this->getJson('/api/stakeholders?per_page=5');

        // Then pagination should work correctly
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $this->assertEquals(5, $response->json('per_page'));
        $this->assertEquals(22, $response->json('total')); // 2 original + 20 created
    }

    /** @test */
    public function it_includes_releases_when_requested()
    {
        // Given a stakeholder with releases
        Sanctum::actingAs($this->user);

        $release = Release::factory()->create(['user_id' => $this->user->id]);
        StakeholderRelease::create([
            'stakeholder_id' => $this->stakeholder1->id,
            'release_id' => $release->id,
            'role' => 'owner',
        ]);

        // When requesting stakeholder with releases
        $response = $this->getJson("/api/stakeholders/{$this->stakeholder1->id}?include=releases");

        // Then releases should be included in response
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'releases' => [
                        '*' => [
                            'id',
                            'name',
                            'pivot' => [
                                'role',
                                'notification_preference',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_bulk_update_stakeholder_availability()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        $stakeholderIds = [$this->stakeholder1->id, $this->stakeholder2->id];

        // When bulk updating availability
        $response = $this->putJson('/api/stakeholders/bulk-update', [
            'stakeholder_ids' => $stakeholderIds,
            'updates' => [
                'is_available' => false,
                'unavailable_until' => now()->addWeeks(2)->toDateString(),
            ],
        ]);

        // Then all specified stakeholders should be updated
        $response->assertStatus(Response::HTTP_OK);

        foreach ($stakeholderIds as $id) {
            $this->assertDatabaseHas('stakeholders', [
                'id' => $id,
                'is_available' => false,
            ]);
        }
    }

    /** @test */
    public function it_prevents_bulk_update_of_other_users_stakeholders()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When trying to bulk update including another user's stakeholder
        $response = $this->putJson('/api/stakeholders/bulk-update', [
            'stakeholder_ids' => [$this->stakeholder1->id, $this->otherUserStakeholder->id],
            'updates' => [
                'is_available' => false,
            ],
        ]);

        // Then only accessible stakeholders should be updated
        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('stakeholders', [
            'id' => $this->stakeholder1->id,
            'is_available' => false,
        ]);

        $this->assertDatabaseHas('stakeholders', [
            'id' => $this->otherUserStakeholder->id,
            'is_available' => true, // Should remain unchanged
        ]);
    }

    /** @test */
    public function it_can_export_stakeholders_to_csv()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When requesting CSV export
        $response = $this->getJson('/api/stakeholders/export?format=csv');

        // Then a CSV file should be returned
        $response->assertStatus(Response::HTTP_OK)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="stakeholders.csv"');

        // And only current user's stakeholders should be included
        $content = $response->getContent();
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('Jane Smith', $content);
        $this->assertStringNotContainsString('Other Stakeholder', $content);
    }

    /** @test */
    public function it_returns_proper_api_response_format()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When requesting stakeholders
        $response = $this->getJson('/api/stakeholders');

        // Then response should follow API format standards
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'title',
                        'company',
                        'department',
                        'phone',
                        'slack_handle',
                        'teams_handle',
                        'preferred_communication_channel',
                        'communication_frequency',
                        'tags',
                        'stakeholder_notes',
                        'last_contact_at',
                        'last_contact_channel',
                        'influence_level',
                        'support_level',
                        'timezone',
                        'is_available',
                        'unavailable_until',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_handles_validation_errors_gracefully()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When sending invalid data
        $response = $this->postJson('/api/stakeholders', [
            'name' => '', // Invalid: empty name
            'email' => 'invalid-email', // Invalid: bad email format
            'influence_level' => 'invalid', // Invalid: not in enum
        ]);

        // Then validation errors should be returned in proper format
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'email',
                    'influence_level',
                ],
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_stakeholders()
    {
        // Given an authenticated user
        Sanctum::actingAs($this->user);

        // When requesting a non-existent stakeholder
        $response = $this->getJson('/api/stakeholders/999999');

        // Then 404 should be returned
        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonStructure([
                'message',
            ]);
    }
}