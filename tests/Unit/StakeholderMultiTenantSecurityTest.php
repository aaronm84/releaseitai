<?php

namespace Tests\Unit;

use App\Models\Stakeholder;
use App\Models\User;
use App\Models\Release;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

/**
 * Test suite for multi-tenant security in the Stakeholder system.
 *
 * This test suite ensures that:
 * - Users can only access their own stakeholders
 * - Stakeholders are automatically scoped to the authenticated user
 * - Cross-tenant data leakage is prevented
 * - Authorization checks are properly implemented
 * - Global scopes protect against accidental data exposure
 */
class StakeholderMultiTenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private User $userC;
    private Stakeholder $stakeholderA1;
    private Stakeholder $stakeholderA2;
    private Stakeholder $stakeholderB1;
    private Stakeholder $stakeholderC1;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->userA = User::factory()->create(['email' => 'usera@example.com']);
        $this->userB = User::factory()->create(['email' => 'userb@example.com']);
        $this->userC = User::factory()->create(['email' => 'userc@example.com']);

        // Create stakeholders for each user
        $this->stakeholderA1 = Stakeholder::create([
            'name' => 'Stakeholder A1',
            'email' => 'stakeholder.a1@example.com',
            'user_id' => $this->userA->id,
            'company' => 'Company A',
            'influence_level' => 'high',
        ]);

        $this->stakeholderA2 = Stakeholder::create([
            'name' => 'Stakeholder A2',
            'email' => 'stakeholder.a2@example.com',
            'user_id' => $this->userA->id,
            'company' => 'Company A',
            'influence_level' => 'medium',
        ]);

        $this->stakeholderB1 = Stakeholder::create([
            'name' => 'Stakeholder B1',
            'email' => 'stakeholder.b1@example.com',
            'user_id' => $this->userB->id,
            'company' => 'Company B',
            'influence_level' => 'high',
        ]);

        $this->stakeholderC1 = Stakeholder::create([
            'name' => 'Stakeholder C1',
            'email' => 'stakeholder.c1@example.com',
            'user_id' => $this->userC->id,
            'company' => 'Company C',
            'influence_level' => 'low',
        ]);
    }

    /** @test */
    public function users_can_only_see_their_own_stakeholders()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When querying all stakeholders
        $stakeholders = Stakeholder::all();

        // Then only User A's stakeholders should be returned
        $this->assertEquals(2, $stakeholders->count());
        $this->assertTrue($stakeholders->contains($this->stakeholderA1));
        $this->assertTrue($stakeholders->contains($this->stakeholderA2));
        $this->assertFalse($stakeholders->contains($this->stakeholderB1));
        $this->assertFalse($stakeholders->contains($this->stakeholderC1));
    }

    /** @test */
    public function global_scope_automatically_filters_stakeholders_by_current_user()
    {
        // Given User B is authenticated
        Auth::login($this->userB);

        // When using various query methods
        $allStakeholders = Stakeholder::all();
        $countStakeholders = Stakeholder::count();
        $whereStakeholders = Stakeholder::where('influence_level', 'high')->get();
        $firstStakeholder = Stakeholder::first();

        // Then all queries should be scoped to User B
        $this->assertEquals(1, $allStakeholders->count());
        $this->assertEquals(1, $countStakeholders);
        $this->assertEquals(1, $whereStakeholders->count());
        $this->assertEquals($this->stakeholderB1->id, $firstStakeholder->id);

        // And User B's stakeholder should be the only one visible
        $this->assertTrue($allStakeholders->contains($this->stakeholderB1));
        $this->assertTrue($whereStakeholders->contains($this->stakeholderB1));
    }

    /** @test */
    public function users_cannot_access_other_users_stakeholders_by_id()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When trying to find User B's stakeholder by ID
        $result = Stakeholder::find($this->stakeholderB1->id);

        // Then it should return null (not found due to global scope)
        $this->assertNull($result);
    }

    /** @test */
    public function find_or_fail_throws_exception_for_other_users_stakeholders()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When trying to find User B's stakeholder with findOrFail
        $this->expectException(ModelNotFoundException::class);

        Stakeholder::findOrFail($this->stakeholderB1->id);
    }

    /** @test */
    public function stakeholder_creation_automatically_assigns_current_user()
    {
        // Given User C is authenticated
        Auth::login($this->userC);

        // When creating a stakeholder without explicitly setting user_id
        $stakeholder = Stakeholder::create([
            'name' => 'Auto Assigned Stakeholder',
            'email' => 'auto@example.com',
        ]);

        // Then the stakeholder should be automatically assigned to User C
        $this->assertEquals($this->userC->id, $stakeholder->user_id);
        $this->assertEquals($this->userC->id, $stakeholder->fresh()->user_id);
    }

    /** @test */
    public function stakeholder_updates_preserve_tenant_isolation()
    {
        // Given User A is authenticated and owns stakeholder A1
        Auth::login($this->userA);

        // When updating the stakeholder
        $this->stakeholderA1->update([
            'name' => 'Updated Stakeholder Name',
            'company' => 'Updated Company',
        ]);

        // Then the update should succeed and preserve user_id
        $updatedStakeholder = Stakeholder::find($this->stakeholderA1->id);
        $this->assertEquals('Updated Stakeholder Name', $updatedStakeholder->name);
        $this->assertEquals('Updated Company', $updatedStakeholder->company);
        $this->assertEquals($this->userA->id, $updatedStakeholder->user_id);
    }

    /** @test */
    public function users_cannot_modify_other_users_stakeholders_via_mass_assignment()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When trying to update with another user's stakeholder ID
        // This should not affect any records due to global scope
        $affectedRows = Stakeholder::where('id', $this->stakeholderB1->id)
            ->update(['name' => 'Attempted Unauthorized Update']);

        // Then no rows should be affected
        $this->assertEquals(0, $affectedRows);

        // And the original stakeholder should remain unchanged
        $originalStakeholder = Stakeholder::withoutGlobalScopes()->find($this->stakeholderB1->id);
        $this->assertEquals('Stakeholder B1', $originalStakeholder->name);
    }

    /** @test */
    public function stakeholder_deletion_respects_tenant_boundaries()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When trying to delete User B's stakeholder
        $deleteResult = Stakeholder::destroy($this->stakeholderB1->id);

        // Then no stakeholders should be deleted
        $this->assertEquals(0, $deleteResult);

        // And User B's stakeholder should still exist
        $stakeholderStillExists = Stakeholder::withoutGlobalScopes()
            ->find($this->stakeholderB1->id);
        $this->assertNotNull($stakeholderStillExists);
    }

    /** @test */
    public function search_functionality_respects_tenant_boundaries()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When searching for stakeholders by name across all users
        $searchResults = Stakeholder::search('Stakeholder')->get();

        // Then only User A's stakeholders should be in results
        $this->assertEquals(2, $searchResults->count());
        $this->assertTrue($searchResults->contains($this->stakeholderA1));
        $this->assertTrue($searchResults->contains($this->stakeholderA2));
        $this->assertFalse($searchResults->contains($this->stakeholderB1));
        $this->assertFalse($searchResults->contains($this->stakeholderC1));
    }

    /** @test */
    public function filtering_by_attributes_respects_tenant_boundaries()
    {
        // Given User C is authenticated (has a stakeholder with low influence)
        Auth::login($this->userC);

        // When filtering by influence level that exists in other tenants
        $highInfluenceStakeholders = Stakeholder::where('influence_level', 'high')->get();
        $lowInfluenceStakeholders = Stakeholder::where('influence_level', 'low')->get();

        // Then only stakeholders from current user should be returned
        $this->assertEquals(0, $highInfluenceStakeholders->count()); // User C has no high influence stakeholders
        $this->assertEquals(1, $lowInfluenceStakeholders->count());
        $this->assertTrue($lowInfluenceStakeholders->contains($this->stakeholderC1));
    }

    /** @test */
    public function stakeholder_relationships_respect_tenant_boundaries()
    {
        // Given releases and stakeholder relationships
        $releaseA = Release::factory()->create(['user_id' => $this->userA->id]);
        $releaseB = Release::factory()->create(['user_id' => $this->userB->id]);

        // Create relationships
        StakeholderRelease::create([
            'stakeholder_id' => $this->stakeholderA1->id,
            'release_id' => $releaseA->id,
            'role' => 'owner',
        ]);

        StakeholderRelease::create([
            'stakeholder_id' => $this->stakeholderB1->id,
            'release_id' => $releaseB->id,
            'role' => 'owner',
        ]);

        // When User A queries stakeholder releases
        Auth::login($this->userA);
        $userAStakeholderReleases = StakeholderRelease::whereHas('stakeholder')->get();

        // Then only relationships for User A's stakeholders should be visible
        $this->assertEquals(1, $userAStakeholderReleases->count());
        $this->assertEquals($this->stakeholderA1->id, $userAStakeholderReleases->first()->stakeholder_id);
    }

    /** @test */
    public function aggregation_queries_respect_tenant_boundaries()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When performing aggregation queries
        $totalCount = Stakeholder::count();
        $highInfluenceCount = Stakeholder::where('influence_level', 'high')->count();
        $companies = Stakeholder::distinct('company')->pluck('company');

        // Then results should be scoped to User A only
        $this->assertEquals(2, $totalCount);
        $this->assertEquals(1, $highInfluenceCount);
        $this->assertEquals(['Company A'], $companies->toArray());
    }

    /** @test */
    public function unauthenticated_users_cannot_access_any_stakeholders()
    {
        // Given no user is authenticated
        Auth::logout();

        // When trying to access stakeholders
        $this->expectException(AuthorizationException::class);

        Stakeholder::all();
    }

    /** @test */
    public function switching_users_properly_changes_scope()
    {
        // Given User A is authenticated
        Auth::login($this->userA);
        $userAStakeholders = Stakeholder::all();

        // When switching to User B
        Auth::login($this->userB);
        $userBStakeholders = Stakeholder::all();

        // Then the scope should change appropriately
        $this->assertEquals(2, $userAStakeholders->count());
        $this->assertEquals(1, $userBStakeholders->count());
        $this->assertNotEquals($userAStakeholders->pluck('id'), $userBStakeholders->pluck('id'));
    }

    /** @test */
    public function raw_queries_with_global_scopes_still_respect_tenant_boundaries()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When using whereRaw or similar raw query methods
        $rawResults = Stakeholder::whereRaw("influence_level = 'high'")->get();
        $whereInResults = Stakeholder::whereIn('influence_level', ['high', 'medium', 'low'])->get();

        // Then results should still be scoped to current user
        $this->assertEquals(1, $rawResults->count());
        $this->assertEquals(2, $whereInResults->count());
        $this->assertTrue($rawResults->contains($this->stakeholderA1));
        $this->assertFalse($rawResults->contains($this->stakeholderB1));
    }

    /** @test */
    public function stakeholder_exists_checks_respect_tenant_boundaries()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When checking if stakeholders exist
        $userAStakeholderExists = Stakeholder::where('id', $this->stakeholderA1->id)->exists();
        $userBStakeholderExists = Stakeholder::where('id', $this->stakeholderB1->id)->exists();

        // Then only current user's stakeholders should be found
        $this->assertTrue($userAStakeholderExists);
        $this->assertFalse($userBStakeholderExists);
    }

    /** @test */
    public function pagination_respects_tenant_boundaries()
    {
        // Given User A is authenticated
        Auth::login($this->userA);

        // When paginating stakeholders
        $paginatedResults = Stakeholder::paginate(10);

        // Then pagination should only include current user's stakeholders
        $this->assertEquals(2, $paginatedResults->total());
        $this->assertEquals(2, $paginatedResults->count());
        $this->assertTrue($paginatedResults->contains($this->stakeholderA1));
        $this->assertTrue($paginatedResults->contains($this->stakeholderA2));
    }

    /** @test */
    public function chunk_processing_respects_tenant_boundaries()
    {
        // Given User B is authenticated
        Auth::login($this->userB);

        $processedStakeholders = collect();

        // When chunking through stakeholders
        Stakeholder::chunk(100, function ($stakeholders) use ($processedStakeholders) {
            $processedStakeholders->push(...$stakeholders);
        });

        // Then only current user's stakeholders should be processed
        $this->assertEquals(1, $processedStakeholders->count());
        $this->assertTrue($processedStakeholders->contains($this->stakeholderB1));
        $this->assertFalse($processedStakeholders->contains($this->stakeholderA1));
    }
}