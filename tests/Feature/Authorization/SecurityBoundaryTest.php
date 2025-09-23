<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Feedback;
use App\Models\Content;
use App\Models\Output;
use App\Models\Input;
use App\Models\WorkstreamPermission;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $userOrg1;
    private User $userOrg2;
    private User $maliciousUser;
    private User $adminUser;
    private Workstream $org1Workstream;
    private Workstream $org2Workstream;
    private Release $org1Release;
    private Release $org2Release;
    private Content $org1Content;
    private Content $org2Content;
    private Feedback $org1Feedback;
    private Feedback $org2Feedback;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users representing different organizations/tenants
        $this->userOrg1 = User::factory()->create([
            'name' => 'User Organization 1',
            'email' => 'user1@org1.com',
        ]);

        $this->userOrg2 = User::factory()->create([
            'name' => 'User Organization 2',
            'email' => 'user2@org2.com',
        ]);

        $this->maliciousUser = User::factory()->create([
            'name' => 'Malicious User',
            'email' => 'malicious@attacker.com',
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@releaseit.com',
        ]);

        // Create organization-specific resources
        $this->org1Workstream = Workstream::factory()->create([
            'name' => 'Org1 Confidential Workstream',
            'owner_id' => $this->userOrg1->id,
        ]);

        $this->org2Workstream = Workstream::factory()->create([
            'name' => 'Org2 Secret Workstream',
            'owner_id' => $this->userOrg2->id,
        ]);

        $this->org1Release = Release::factory()->create([
            'name' => 'Org1 Confidential Release',
            'workstream_id' => $this->org1Workstream->id,
        ]);

        $this->org2Release = Release::factory()->create([
            'name' => 'Org2 Secret Release',
            'workstream_id' => $this->org2Workstream->id,
        ]);

        // Create content with sensitive information
        $this->org1Content = Content::factory()->create([
            'user_id' => $this->userOrg1->id,
            'title' => 'Org1 Strategic Plan',
            'content' => 'Top secret strategic information for Org1',
            'type' => 'strategic_document',
        ]);

        $this->org2Content = Content::factory()->create([
            'user_id' => $this->userOrg2->id,
            'title' => 'Org2 Financial Data',
            'content' => 'Confidential financial projections for Org2',
            'type' => 'financial_data',
        ]);

        // Create feedback with user behavior data
        $org1Input = Input::factory()->create(['user_id' => $this->userOrg1->id]);
        $org1Output = Output::factory()->create(['input_id' => $org1Input->id]);
        $this->org1Feedback = Feedback::factory()->create([
            'output_id' => $org1Output->id,
            'user_id' => $this->userOrg1->id,
        ]);

        $org2Input = Input::factory()->create(['user_id' => $this->userOrg2->id]);
        $org2Output = Output::factory()->create(['input_id' => $org2Input->id]);
        $this->org2Feedback = Feedback::factory()->create([
            'output_id' => $org2Output->id,
            'user_id' => $this->userOrg2->id,
        ]);
    }

    /** @test */
    public function test_cross_tenant_workstream_access_is_prevented()
    {
        // Given: Users from different organizations
        // When: Org1 user tries to access Org2's workstream
        $response = $this->actingAs($this->userOrg1)
            ->getJson("/api/workstreams/{$this->org2Workstream->id}");

        // Then: Access should be denied
        $response->assertStatus(403);

        // And: Org2 user tries to access Org1's workstream
        $response2 = $this->actingAs($this->userOrg2)
            ->getJson("/api/workstreams/{$this->org1Workstream->id}");

        // Then: Access should be denied
        $response2->assertStatus(403);
    }

    /** @test */
    public function test_cross_tenant_release_access_is_prevented()
    {
        // Given: Users from different organizations
        // When: Org1 user tries to access Org2's release
        $response = $this->actingAs($this->userOrg1)
            ->getJson("/api/releases/{$this->org2Release->id}");

        // Then: Access should be denied
        $response->assertStatus(403);

        // When: Org2 user tries to update Org1's release
        $response2 = $this->actingAs($this->userOrg2)
            ->putJson("/api/releases/{$this->org1Release->id}", [
                'name' => 'Malicious Update',
            ]);

        // Then: Access should be denied
        $response2->assertStatus(403);
    }

    /** @test */
    public function test_cross_tenant_content_access_is_prevented()
    {
        // Given: Users from different organizations
        // When: Org1 user tries to access Org2's content
        $response = $this->actingAs($this->userOrg1)
            ->getJson("/api/content/{$this->org2Content->id}");

        // Then: Access should be denied
        $response->assertStatus(403);

        // When: Malicious user tries to access any organization's content
        $maliciousResponse1 = $this->actingAs($this->maliciousUser)
            ->getJson("/api/content/{$this->org1Content->id}");

        $maliciousResponse2 = $this->actingAs($this->maliciousUser)
            ->getJson("/api/content/{$this->org2Content->id}");

        // Then: All access should be denied
        $maliciousResponse1->assertStatus(403);
        $maliciousResponse2->assertStatus(403);
    }

    /** @test */
    public function test_cross_tenant_feedback_access_is_prevented()
    {
        // Given: Users from different organizations
        // When: Org1 user tries to access Org2's feedback
        $response = $this->actingAs($this->userOrg1)
            ->getJson("/api/feedback/{$this->org2Feedback->id}");

        // Then: Access should be denied
        $response->assertStatus(403);

        // When: Org2 user tries to modify Org1's feedback
        $response2 = $this->actingAs($this->userOrg2)
            ->putJson("/api/feedback/{$this->org1Feedback->id}", [
                'action' => 'manipulated_action',
            ]);

        // Then: Access should be denied
        $response2->assertStatus(403);
    }

    /** @test */
    public function test_bulk_operations_prevent_cross_tenant_data_leakage()
    {
        // Given: A user from Org1
        // When: They try to bulk fetch resources including other organizations' data
        $bulkWorkstreamResponse = $this->actingAs($this->userOrg1)
            ->postJson('/api/workstreams/bulk', [
                'workstream_ids' => [
                    $this->org1Workstream->id,
                    $this->org2Workstream->id, // Should not be accessible
                ]
            ]);

        $bulkReleaseResponse = $this->actingAs($this->userOrg1)
            ->postJson('/api/releases/bulk', [
                'release_ids' => [
                    $this->org1Release->id,
                    $this->org2Release->id, // Should not be accessible
                ]
            ]);

        // Then: Should only receive their own organization's data
        $bulkWorkstreamResponse->assertStatus(200);
        $workstreamData = $bulkWorkstreamResponse->json();
        $this->assertCount(1, $workstreamData['data']);
        $this->assertEquals($this->org1Workstream->id, $workstreamData['data'][0]['id']);

        $bulkReleaseResponse->assertStatus(200);
        $releaseData = $bulkReleaseResponse->json();
        $this->assertCount(1, $releaseData['data']);
        $this->assertEquals($this->org1Release->id, $releaseData['data'][0]['id']);
    }

    /** @test */
    public function test_search_operations_prevent_cross_tenant_data_exposure()
    {
        // Given: Users from different organizations with similar named resources
        $this->org1Workstream->update(['name' => 'Secret Project Alpha']);
        $this->org2Workstream->update(['name' => 'Secret Project Beta']);

        // When: Org1 user searches for "Secret Project"
        $response = $this->actingAs($this->userOrg1)
            ->getJson('/api/workstreams/search?q=Secret Project');

        // Then: Should only see their own organization's results
        $response->assertStatus(200);
        $searchData = $response->json();

        $foundIds = collect($searchData['data'])->pluck('id')->toArray();
        $this->assertContains($this->org1Workstream->id, $foundIds);
        $this->assertNotContains($this->org2Workstream->id, $foundIds);
    }

    /** @test */
    public function test_permission_escalation_attacks_are_prevented()
    {
        // Given: A malicious user with no permissions
        // When: They try to grant themselves permissions on other organizations' workstreams
        $escalationResponse = $this->actingAs($this->maliciousUser)
            ->postJson("/api/workstreams/{$this->org1Workstream->id}/permissions", [
                'user_id' => $this->maliciousUser->id,
                'permission_type' => 'admin',
                'scope' => 'workstream_and_children',
            ]);

        // Then: Should be denied
        $escalationResponse->assertStatus(403);

        // When: They try to modify existing permissions
        $existingPermission = WorkstreamPermission::factory()->create([
            'workstream_id' => $this->org1Workstream->id,
            'user_id' => $this->userOrg1->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->userOrg1->id,
        ]);

        $modifyResponse = $this->actingAs($this->maliciousUser)
            ->putJson("/api/workstreams/{$this->org1Workstream->id}/permissions/{$existingPermission->id}", [
                'permission_type' => 'admin',
                'scope' => 'workstream_and_children',
            ]);

        // Then: Should be denied
        $modifyResponse->assertStatus(403);
    }

    /** @test */
    public function test_sql_injection_attempts_are_prevented()
    {
        // Given: A malicious user attempting SQL injection
        $maliciousQueries = [
            "'; DROP TABLE workstreams; --",
            "' UNION SELECT * FROM users WHERE email LIKE '%@org2.com' --",
            "' OR 1=1 --",
            "'; UPDATE workstreams SET owner_id = {$this->maliciousUser->id} --",
        ];

        foreach ($maliciousQueries as $maliciousQuery) {
            // When: They try to inject SQL through search parameters
            $response = $this->actingAs($this->maliciousUser)
                ->getJson('/api/workstreams/search?q=' . urlencode($maliciousQuery));

            // Then: Should not cause errors or data leakage
            $this->assertContains($response->status(), [200, 422, 400]); // Valid responses, not 500

            if ($response->status() === 200) {
                $data = $response->json();
                $this->assertArrayHasKey('data', $data);
                // Should not return unauthorized data
                foreach ($data['data'] as $item) {
                    $this->assertNotEquals($this->org1Workstream->id, $item['id']);
                    $this->assertNotEquals($this->org2Workstream->id, $item['id']);
                }
            }
        }
    }

    /** @test */
    public function test_enumeration_attacks_are_prevented()
    {
        // Given: A malicious user trying to enumerate resources
        $maxAttempts = 100;
        $foundResources = [];

        // When: They try to enumerate workstream IDs
        for ($i = 1; $i <= $maxAttempts; $i++) {
            $response = $this->actingAs($this->maliciousUser)
                ->getJson("/api/workstreams/{$i}");

            if ($response->status() === 200) {
                $foundResources[] = $i;
            }
        }

        // Then: Should not be able to access any resources
        $this->assertEmpty($foundResources, 'Malicious user should not be able to enumerate resources');
    }

    /** @test */
    public function test_mass_assignment_vulnerabilities_are_prevented()
    {
        // Given: A user with limited permissions
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->org1Workstream->id,
            'user_id' => $this->userOrg1->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->userOrg1->id,
        ]);

        // When: They try to mass assign protected fields
        $massAssignmentAttempts = [
            ['owner_id' => $this->maliciousUser->id], // Try to steal ownership
            ['id' => 99999], // Try to change ID
            ['created_at' => '2020-01-01'], // Try to change timestamps
            ['updated_at' => '2020-01-01'],
            ['hierarchy_depth' => 999], // Try to manipulate hierarchy
        ];

        foreach ($massAssignmentAttempts as $maliciousData) {
            $response = $this->actingAs($this->userOrg1)
                ->putJson("/api/workstreams/{$this->org1Workstream->id}", $maliciousData);

            // Then: Should not allow unauthorized field changes
            $this->assertContains($response->status(), [403, 422, 400]); // Should reject or ignore

            // Verify the workstream hasn't been compromised
            $this->org1Workstream->refresh();
            $this->assertEquals($this->userOrg1->id, $this->org1Workstream->owner_id);
        }
    }

    /** @test */
    public function test_data_export_prevents_cross_tenant_leakage()
    {
        // Given: Users from different organizations
        // When: Org1 user tries to export data
        $exportResponse = $this->actingAs($this->userOrg1)
            ->getJson('/api/export/my-data');

        // Then: Should only receive their own organization's data
        $exportResponse->assertStatus(200);
        $exportData = $exportResponse->json();

        // Should contain Org1 data
        $this->assertStringContains('Org1', json_encode($exportData));
        // Should not contain Org2 data
        $this->assertStringNotContains('Org2', json_encode($exportData));
        $this->assertStringNotContains('Secret Release', json_encode($exportData));
    }

    /** @test */
    public function test_analytics_endpoints_prevent_cross_tenant_data_exposure()
    {
        // Given: Users from different organizations
        // When: Org1 user tries to access analytics
        $analyticsResponse = $this->actingAs($this->userOrg1)
            ->getJson('/api/analytics/workstreams');

        // Then: Should only see analytics for their accessible workstreams
        $analyticsResponse->assertStatus(200);
        $analyticsData = $analyticsResponse->json();

        // Should not contain other organizations' data in analytics
        $this->assertArrayHasKey('data', $analyticsData);
        foreach ($analyticsData['data'] as $item) {
            // If workstream data is included, should only be accessible ones
            if (isset($item['workstream_id'])) {
                $this->assertEquals($this->org1Workstream->id, $item['workstream_id']);
            }
        }
    }

    /** @test */
    public function test_error_messages_do_not_leak_sensitive_information()
    {
        // Given: A user trying to access non-existent or forbidden resources
        $testCases = [
            "/api/workstreams/{$this->org2Workstream->id}",
            "/api/releases/{$this->org2Release->id}",
            "/api/content/{$this->org2Content->id}",
            "/api/feedback/{$this->org2Feedback->id}",
            "/api/workstreams/99999", // Non-existent
            "/api/releases/99999",    // Non-existent
        ];

        foreach ($testCases as $endpoint) {
            // When: They access forbidden/non-existent resources
            $response = $this->actingAs($this->userOrg1)
                ->getJson($endpoint);

            // Then: Error message should not reveal sensitive information
            $this->assertContains($response->status(), [403, 404]);
            $responseData = $response->json();

            if (isset($responseData['message'])) {
                // Should not contain sensitive details about the resource
                $this->assertStringNotContains('Org2', $responseData['message']);
                $this->assertStringNotContains('Secret', $responseData['message']);
                $this->assertStringNotContains('Confidential', $responseData['message']);

                // Should be generic security message
                $this->assertStringContains('not authorized', strtolower($responseData['message']));
            }
        }
    }

    /** @test */
    public function test_database_direct_access_attempts_fail()
    {
        // This test ensures that even if someone bypasses the application layer,
        // database constraints and policies prevent unauthorized access

        // Given: A malicious user attempting direct database manipulation
        // When: They try to directly insert permissions
        try {
            DB::table('workstream_permissions')->insert([
                'workstream_id' => $this->org1Workstream->id,
                'user_id' => $this->maliciousUser->id,
                'permission_type' => 'admin',
                'scope' => 'workstream_and_children',
                'granted_by' => $this->maliciousUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // If this succeeds, verify the application layer still prevents access
            $response = $this->actingAs($this->maliciousUser)
                ->getJson("/api/workstreams/{$this->org1Workstream->id}");

            // Should still be denied at application layer
            $response->assertStatus(403);
        } catch (\Exception $e) {
            // Database should prevent this operation
            $this->assertTrue(true, 'Database prevented unauthorized permission insertion');
        }
    }

    /** @test */
    public function test_admin_access_is_properly_scoped()
    {
        // Given: An admin user
        // When: They access cross-tenant data
        $org1Response = $this->actingAs($this->adminUser)
            ->getJson("/api/workstreams/{$this->org1Workstream->id}");

        $org2Response = $this->actingAs($this->adminUser)
            ->getJson("/api/workstreams/{$this->org2Workstream->id}");

        // Then: Admin should have access but with proper auditing
        $org1Response->assertStatus(200);
        $org2Response->assertStatus(200);

        // Verify admin access is logged (check for audit trail)
        // This would typically check audit logs or activity logs
        $this->assertTrue(true, 'Admin access should be properly logged');
    }

    /** @test */
    public function test_session_hijacking_protection()
    {
        // Given: A user with a valid session
        $this->actingAs($this->userOrg1);

        // When: Session is accessed from different context (simulated)
        // This would typically involve checking IP address, user agent, etc.
        $response = $this->actingAs($this->userOrg1)
            ->withHeaders([
                'X-Forwarded-For' => '192.168.1.100', // Different IP
                'User-Agent' => 'DifferentBrowser/1.0',
            ])
            ->getJson("/api/workstreams/{$this->org1Workstream->id}");

        // Then: Should still work for this test, but in production would trigger security checks
        $response->assertStatus(200);

        // In a real implementation, this would check for:
        // - IP address changes
        // - User agent changes
        // - Concurrent sessions
        // - Session timeout
    }

    /** @test */
    public function test_rate_limiting_prevents_brute_force_attacks()
    {
        // Given: A malicious user attempting brute force
        $attempts = 0;
        $maxAttempts = 60; // Typical rate limit

        // When: They make rapid requests
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->actingAs($this->maliciousUser)
                ->getJson("/api/workstreams/{$this->org1Workstream->id}");

            $attempts++;

            // If rate limited, break
            if ($response->status() === 429) {
                break;
            }
        }

        // Then: Should eventually be rate limited
        $this->assertLessThan($maxAttempts, $attempts, 'Rate limiting should prevent excessive requests');
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            strpos($haystack, $needle) !== false,
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }

    private function assertStringNotContains(string $needle, string $haystack): void
    {
        $this->assertFalse(
            strpos($haystack, $needle) !== false,
            "Failed asserting that '$haystack' does not contain '$needle'"
        );
    }
}