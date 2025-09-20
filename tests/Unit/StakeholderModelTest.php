<?php

namespace Tests\Unit;

use App\Models\Stakeholder;
use App\Models\User;
use App\Models\Release;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Test suite for the new Stakeholder model functionality.
 *
 * This test suite defines the expected behavior for the dedicated Stakeholder model
 * that will replace stakeholder functionality currently stored in the users table.
 *
 * Key requirements being tested:
 * - Stakeholder creation with required fields
 * - Multi-tenant data isolation
 * - Stakeholder relationships (releases, interactions)
 * - Validation rules (email format, required fields)
 * - Automatic scoping to authenticated user
 */
class StakeholderModelTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'email' => 'owner@example.com',
            'name' => 'Release Owner'
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'other@example.com',
            'name' => 'Other User'
        ]);
    }

    /** @test */
    public function it_can_create_a_stakeholder_with_required_fields()
    {
        // Given an authenticated user
        Auth::login($this->owner);

        // When creating a stakeholder with minimum required fields
        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id, // For tenant isolation
        ]);

        // Then the stakeholder should be created successfully
        $this->assertInstanceOf(Stakeholder::class, $stakeholder);
        $this->assertEquals('John Doe', $stakeholder->name);
        $this->assertEquals('john.doe@example.com', $stakeholder->email);
        $this->assertEquals($this->owner->id, $stakeholder->user_id);
        $this->assertDatabaseHas('stakeholders', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ]);
    }

    /** @test */
    public function it_requires_name_field()
    {
        $this->expectException(ValidationException::class);

        // When creating a stakeholder without a name
        Stakeholder::create([
            'email' => 'test@example.com',
            'user_id' => $this->owner->id,
        ]);
    }

    /** @test */
    public function it_requires_email_field()
    {
        $this->expectException(ValidationException::class);

        // When creating a stakeholder without an email
        Stakeholder::create([
            'name' => 'John Doe',
            'user_id' => $this->owner->id,
        ]);
    }

    /** @test */
    public function it_requires_user_id_field_for_tenant_isolation()
    {
        $this->expectException(ValidationException::class);

        // When creating a stakeholder without a user_id
        Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $this->expectException(ValidationException::class);

        // When creating a stakeholder with invalid email format
        Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'user_id' => $this->owner->id,
        ]);
    }

    /** @test */
    public function it_enforces_unique_email_per_tenant()
    {
        // Given a stakeholder already exists for a user
        Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ]);

        $this->expectException(ValidationException::class);

        // When trying to create another stakeholder with same email for same user
        Stakeholder::create([
            'name' => 'Jane Doe',
            'email' => 'john.doe@example.com', // Same email
            'user_id' => $this->owner->id, // Same user
        ]);
    }

    /** @test */
    public function it_allows_same_email_for_different_tenants()
    {
        // Given a stakeholder exists for one user
        $stakeholder1 = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ]);

        // When creating a stakeholder with same email for different user
        $stakeholder2 = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com', // Same email
            'user_id' => $this->otherUser->id, // Different user
        ]);

        // Then both stakeholders should exist
        $this->assertNotEquals($stakeholder1->id, $stakeholder2->id);
        $this->assertEquals(2, Stakeholder::count());
    }

    /** @test */
    public function it_can_store_all_stakeholder_fields()
    {
        // When creating a stakeholder with all available fields
        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
            'title' => 'Senior Manager',
            'company' => 'ACME Corp',
            'department' => 'IT Operations',
            'phone' => '+1-555-0123',
            'slack_handle' => '@johndoe',
            'teams_handle' => 'john.doe@company.com',
            'preferred_communication_channel' => 'slack',
            'communication_frequency' => 'weekly',
            'tags' => ['vip', 'critical-stakeholder'],
            'stakeholder_notes' => 'Key decision maker for infrastructure changes',
            'influence_level' => 'high',
            'support_level' => 'medium',
            'timezone' => 'America/New_York',
            'is_available' => true,
        ]);

        // Then all fields should be stored correctly
        $this->assertEquals('Senior Manager', $stakeholder->title);
        $this->assertEquals('ACME Corp', $stakeholder->company);
        $this->assertEquals('IT Operations', $stakeholder->department);
        $this->assertEquals('+1-555-0123', $stakeholder->phone);
        $this->assertEquals('@johndoe', $stakeholder->slack_handle);
        $this->assertEquals('john.doe@company.com', $stakeholder->teams_handle);
        $this->assertEquals('slack', $stakeholder->preferred_communication_channel);
        $this->assertEquals('weekly', $stakeholder->communication_frequency);
        $this->assertEquals(['vip', 'critical-stakeholder'], $stakeholder->tags);
        $this->assertEquals('Key decision maker for infrastructure changes', $stakeholder->stakeholder_notes);
        $this->assertEquals('high', $stakeholder->influence_level);
        $this->assertEquals('medium', $stakeholder->support_level);
        $this->assertEquals('America/New_York', $stakeholder->timezone);
        $this->assertTrue($stakeholder->is_available);
    }

    /** @test */
    public function it_validates_enum_fields()
    {
        $validData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ];

        // Test invalid preferred_communication_channel
        $this->expectException(ValidationException::class);
        Stakeholder::create($validData + ['preferred_communication_channel' => 'invalid']);
    }

    /** @test */
    public function it_belongs_to_a_user_for_tenant_isolation()
    {
        // Given a stakeholder
        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ]);

        // When accessing the user relationship
        $user = $stakeholder->user;

        // Then it should return the correct user
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->owner->id, $user->id);
        $this->assertEquals($this->owner->email, $user->email);
    }

    /** @test */
    public function it_can_have_many_release_relationships()
    {
        // Given a stakeholder and releases
        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ]);

        $release1 = Release::factory()->create(['user_id' => $this->owner->id]);
        $release2 = Release::factory()->create(['user_id' => $this->owner->id]);

        // When associating stakeholder with releases through pivot
        StakeholderRelease::create([
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release1->id,
            'role' => 'owner',
            'notification_preference' => 'email',
        ]);

        StakeholderRelease::create([
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release2->id,
            'role' => 'reviewer',
            'notification_preference' => 'slack',
        ]);

        // Then the stakeholder should have relationships with both releases
        $this->assertEquals(2, $stakeholder->releases()->count());
        $this->assertTrue($stakeholder->releases->contains($release1));
        $this->assertTrue($stakeholder->releases->contains($release2));
    }

    /** @test */
    public function it_can_filter_releases_by_role()
    {
        // Given a stakeholder with different roles in releases
        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ]);

        $release1 = Release::factory()->create(['user_id' => $this->owner->id]);
        $release2 = Release::factory()->create(['user_id' => $this->owner->id]);
        $release3 = Release::factory()->create(['user_id' => $this->owner->id]);

        StakeholderRelease::create([
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release1->id,
            'role' => 'owner',
            'notification_preference' => 'email',
        ]);

        StakeholderRelease::create([
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release2->id,
            'role' => 'reviewer',
            'notification_preference' => 'slack',
        ]);

        StakeholderRelease::create([
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release3->id,
            'role' => 'owner',
            'notification_preference' => 'email',
        ]);

        // When filtering releases by role
        $ownerReleases = $stakeholder->releasesByRole('owner');
        $reviewerReleases = $stakeholder->releasesByRole('reviewer');

        // Then only releases with that role should be returned
        $this->assertEquals(2, $ownerReleases->count());
        $this->assertEquals(1, $reviewerReleases->count());
        $this->assertTrue($ownerReleases->contains($release1));
        $this->assertTrue($ownerReleases->contains($release3));
        $this->assertTrue($reviewerReleases->contains($release2));
    }

    /** @test */
    public function it_automatically_scopes_to_authenticated_user()
    {
        // Given stakeholders for different users
        Auth::login($this->owner);

        $ownerStakeholder = Stakeholder::create([
            'name' => 'Owner Stakeholder',
            'email' => 'owner.stakeholder@example.com',
            'user_id' => $this->owner->id,
        ]);

        $otherStakeholder = Stakeholder::create([
            'name' => 'Other Stakeholder',
            'email' => 'other.stakeholder@example.com',
            'user_id' => $this->otherUser->id,
        ]);

        // When querying stakeholders with automatic scoping
        $scopedStakeholders = Stakeholder::forCurrentUser()->get();

        // Then only the current user's stakeholders should be returned
        $this->assertEquals(1, $scopedStakeholders->count());
        $this->assertTrue($scopedStakeholders->contains($ownerStakeholder));
        $this->assertFalse($scopedStakeholders->contains($otherStakeholder));
    }

    /** @test */
    public function it_can_search_stakeholders_by_name()
    {
        // Given stakeholders with different names
        Auth::login($this->owner);

        $stakeholder1 = Stakeholder::create([
            'name' => 'John Smith',
            'email' => 'john.smith@example.com',
            'user_id' => $this->owner->id,
        ]);

        $stakeholder2 = Stakeholder::create([
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'user_id' => $this->owner->id,
        ]);

        $stakeholder3 = Stakeholder::create([
            'name' => 'John Williams',
            'email' => 'john.williams@example.com',
            'user_id' => $this->owner->id,
        ]);

        // When searching by name
        $searchResults = Stakeholder::search('John')->get();

        // Then only matching stakeholders should be returned
        $this->assertEquals(2, $searchResults->count());
        $this->assertTrue($searchResults->contains($stakeholder1));
        $this->assertTrue($searchResults->contains($stakeholder3));
        $this->assertFalse($searchResults->contains($stakeholder2));
    }

    /** @test */
    public function it_can_filter_by_influence_and_support_levels()
    {
        // Given stakeholders with different influence/support levels
        Auth::login($this->owner);

        $highInfluence = Stakeholder::create([
            'name' => 'High Influence',
            'email' => 'high@example.com',
            'user_id' => $this->owner->id,
            'influence_level' => 'high',
            'support_level' => 'medium',
        ]);

        $lowInfluence = Stakeholder::create([
            'name' => 'Low Influence',
            'email' => 'low@example.com',
            'user_id' => $this->owner->id,
            'influence_level' => 'low',
            'support_level' => 'high',
        ]);

        // When filtering by influence level
        $highInfluenceStakeholders = Stakeholder::whereInfluenceLevel('high')->get();
        $lowInfluenceStakeholders = Stakeholder::whereInfluenceLevel('low')->get();

        // Then appropriate stakeholders should be returned
        $this->assertEquals(1, $highInfluenceStakeholders->count());
        $this->assertEquals(1, $lowInfluenceStakeholders->count());
        $this->assertTrue($highInfluenceStakeholders->contains($highInfluence));
        $this->assertTrue($lowInfluenceStakeholders->contains($lowInfluence));
    }

    /** @test */
    public function it_tracks_last_contact_information()
    {
        // Given a stakeholder
        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
        ]);

        $contactTime = now();

        // When updating last contact information
        $stakeholder->update([
            'last_contact_at' => $contactTime,
            'last_contact_channel' => 'slack',
        ]);

        // Then the contact information should be stored
        $this->assertEquals($contactTime->toDateTimeString(), $stakeholder->last_contact_at->toDateTimeString());
        $this->assertEquals('slack', $stakeholder->last_contact_channel);
    }

    /** @test */
    public function it_handles_availability_status()
    {
        // Given a stakeholder
        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'user_id' => $this->owner->id,
            'is_available' => false,
            'unavailable_until' => now()->addWeeks(2),
        ]);

        // Then availability should be tracked correctly
        $this->assertFalse($stakeholder->is_available);
        $this->assertTrue($stakeholder->unavailable_until->isFuture());
    }
}