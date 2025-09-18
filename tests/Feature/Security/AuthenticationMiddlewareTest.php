<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Workstream $workstream;
    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $this->release = Release::factory()->create();
    }

    /** @test */
    public function unauthenticated_users_cannot_access_workstream_endpoints()
    {
        // Given: An unauthenticated user
        // When: Attempting to access workstream endpoints
        // Then: Should receive 401 Unauthorized responses

        $endpoints = [
            ['GET', '/api/workstreams'],
            ['POST', '/api/workstreams'],
            ['GET', "/api/workstreams/{$this->workstream->id}"],
            ['PUT', "/api/workstreams/{$this->workstream->id}"],
            ['DELETE', "/api/workstreams/{$this->workstream->id}"],
            ['GET', "/api/workstreams/{$this->workstream->id}/hierarchy"],
            ['GET', "/api/workstreams/{$this->workstream->id}/rollup-report"],
            ['GET', "/api/workstreams/{$this->workstream->id}/permissions"],
            ['POST', "/api/workstreams/{$this->workstream->id}/permissions"],
            ['PUT', "/api/workstreams/{$this->workstream->id}/move"],
            ['PUT', '/api/workstreams/bulk-update'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url, [
                'name' => 'Test Workstream',
                'type' => 'product_line',
                'owner_id' => $this->user->id,
            ]);

            $response->assertStatus(401, "Failed for {$method} {$url}");
            $response->assertJson([
                'message' => 'Unauthenticated.'
            ]);
        }
    }

    /** @test */
    public function unauthenticated_users_cannot_access_release_stakeholder_endpoints()
    {
        // Given: An unauthenticated user
        // When: Attempting to access release stakeholder endpoints
        // Then: Should receive 401 Unauthorized responses

        $endpoints = [
            ['GET', "/api/releases/{$this->release->id}/stakeholders"],
            ['POST', "/api/releases/{$this->release->id}/stakeholders"],
            ['PUT', "/api/releases/{$this->release->id}/stakeholders/{$this->user->id}"],
            ['DELETE', "/api/releases/{$this->release->id}/stakeholders/{$this->user->id}"],
            ['GET', "/api/stakeholders/{$this->user->id}/releases"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url, [
                'stakeholder_id' => $this->user->id,
                'role' => 'reviewer',
            ]);

            $response->assertStatus(401, "Failed for {$method} {$url}");
            $response->assertJson([
                'message' => 'Unauthenticated.'
            ]);
        }
    }

    /** @test */
    public function unauthenticated_users_cannot_access_checklist_assignment_endpoints()
    {
        // Given: An unauthenticated user
        // When: Attempting to access checklist assignment endpoints
        // Then: Should receive 401 Unauthorized responses

        $endpoints = [
            ['POST', "/api/releases/{$this->release->id}/checklist-assignments"],
            ['GET', "/api/releases/{$this->release->id}/checklist-assignments"],
            ['GET', '/api/checklist-assignments/1'],
            ['PUT', '/api/checklist-assignments/1/reassign'],
            ['POST', '/api/checklist-assignments/1/escalate'],
            ['PUT', '/api/checklist-assignments/1/status'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url, [
                'checklist_item_id' => 1,
                'assigned_to_user_id' => $this->user->id,
            ]);

            $response->assertStatus(401, "Failed for {$method} {$url}");
            $response->assertJson([
                'message' => 'Unauthenticated.'
            ]);
        }
    }

    /** @test */
    public function unauthenticated_users_cannot_access_checklist_dependency_endpoints()
    {
        // Given: An unauthenticated user
        // When: Attempting to access checklist dependency endpoints
        // Then: Should receive 401 Unauthorized responses

        $endpoints = [
            ['POST', '/api/checklist-dependencies'],
            ['GET', '/api/checklist-dependencies/1'],
            ['PUT', '/api/checklist-dependencies/1'],
            ['DELETE', '/api/checklist-dependencies/1'],
            ['GET', '/api/checklist-dependencies/assignment/1'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url, [
                'dependent_assignment_id' => 1,
                'prerequisite_assignment_id' => 2,
            ]);

            $response->assertStatus(401, "Failed for {$method} {$url}");
            $response->assertJson([
                'message' => 'Unauthenticated.'
            ]);
        }
    }

    /** @test */
    public function unauthenticated_users_cannot_access_approval_request_endpoints()
    {
        // Given: An unauthenticated user
        // When: Attempting to access approval request endpoints
        // Then: Should receive 401 Unauthorized responses

        $endpoints = [
            ['POST', "/api/releases/{$this->release->id}/approval-requests"],
            ['GET', "/api/releases/{$this->release->id}/approval-requests"],
            ['GET', "/api/releases/{$this->release->id}/approval-status"],
            ['GET', "/api/workstreams/{$this->workstream->id}/approval-summary"],
            ['POST', '/api/approval-requests/send-reminders'],
            ['POST', '/api/approval-requests/process-expirations'],
            ['PUT', '/api/approval-requests/1'],
            ['POST', '/api/approval-requests/1/respond'],
            ['POST', '/api/approval-requests/1/cancel'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url, [
                'workstream_id' => $this->workstream->id,
                'approver_id' => $this->user->id,
                'approval_type' => 'gate_review',
            ]);

            $response->assertStatus(401, "Failed for {$method} {$url}");
            $response->assertJson([
                'message' => 'Unauthenticated.'
            ]);
        }
    }

    /** @test */
    public function unauthenticated_users_cannot_access_communication_endpoints()
    {
        // Given: An unauthenticated user
        // When: Attempting to access communication endpoints
        // Then: Should receive 401 Unauthorized responses

        $endpoints = [
            ['POST', "/api/releases/{$this->release->id}/communications"],
            ['GET', "/api/releases/{$this->release->id}/communications"],
            ['GET', "/api/releases/{$this->release->id}/communication-analytics"],
            ['GET', '/api/communications/search'],
            ['GET', '/api/communications/follow-ups'],
            ['GET', '/api/communications/1'],
            ['PUT', '/api/communications/1/outcome'],
            ['PUT', '/api/communications/1/participants/1/status'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url, [
                'channel' => 'email',
                'content' => 'Test communication',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'participants' => [
                    ['user_id' => $this->user->id, 'type' => 'primary']
                ]
            ]);

            $response->assertStatus(401, "Failed for {$method} {$url}");
            $response->assertJson([
                'message' => 'Unauthenticated.'
            ]);
        }
    }

    /** @test */
    public function authenticated_users_can_access_protected_endpoints()
    {
        // Given: An authenticated user
        Sanctum::actingAs($this->user);

        // When: Attempting to access workstream endpoints
        $response = $this->getJson('/api/workstreams');

        // Then: Should not receive 401 Unauthorized
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    /** @test */
    public function user_endpoint_requires_sanctum_authentication()
    {
        // Given: An unauthenticated user
        // When: Attempting to access the user endpoint
        $response = $this->getJson('/api/user');

        // Then: Should receive 401 Unauthorized
        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthenticated.'
        ]);
    }

    /** @test */
    public function user_endpoint_returns_authenticated_user_data()
    {
        // Given: An authenticated user with Sanctum
        Sanctum::actingAs($this->user);

        // When: Accessing the user endpoint
        $response = $this->getJson('/api/user');

        // Then: Should return the authenticated user's data
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);
    }

    /** @test */
    public function api_endpoints_reject_invalid_tokens()
    {
        // Given: A user with an invalid/expired token
        $this->withHeader('Authorization', 'Bearer invalid-token-12345');

        // When: Attempting to access protected endpoints
        $response = $this->getJson('/api/workstreams');

        // Then: Should receive 401 Unauthorized
        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthenticated.'
        ]);
    }

    /** @test */
    public function api_endpoints_reject_malformed_authorization_headers()
    {
        // Given: A malformed authorization header
        $malformedHeaders = [
            'Bearer',  // Missing token
            'Token 12345',  // Wrong scheme
            'bearer token123',  // Wrong case
            'Bearer ',  // Empty token
        ];

        foreach ($malformedHeaders as $header) {
            // When: Attempting to access protected endpoints with malformed auth
            $response = $this->withHeader('Authorization', $header)
                ->getJson('/api/workstreams');

            // Then: Should receive 401 Unauthorized
            $response->assertStatus(401, "Failed for header: {$header}");
            $response->assertJson([
                'message' => 'Unauthenticated.'
            ]);
        }
    }

    /** @test */
    public function options_requests_do_not_require_authentication()
    {
        // Given: A CORS preflight OPTIONS request
        // When: Making an OPTIONS request to API endpoints
        $response = $this->call('OPTIONS', '/api/workstreams');

        // Then: Should not require authentication (typically handled by CORS middleware)
        // Note: This test verifies that CORS preflight requests work
        $this->assertTrue(in_array($response->getStatusCode(), [200, 204, 405]));
    }

    /** @test */
    public function authentication_is_consistent_across_all_crud_operations()
    {
        // Given: Various CRUD operations on different resources
        $crudTests = [
            // Workstreams
            ['POST', '/api/workstreams', ['name' => 'Test', 'type' => 'product_line', 'owner_id' => $this->user->id]],
            ['GET', '/api/workstreams'],
            ['PUT', "/api/workstreams/{$this->workstream->id}", ['name' => 'Updated']],
            ['DELETE', "/api/workstreams/{$this->workstream->id}"],
        ];

        foreach ($crudTests as [$method, $url, $data]) {
            // When: Making unauthenticated requests
            $response = $this->json($method, $url, $data ?? []);

            // Then: All should require authentication
            $response->assertStatus(401, "Failed for {$method} {$url}");
        }
    }

    /** @test */
    public function api_does_not_leak_sensitive_information_in_unauthorized_responses()
    {
        // Given: An unauthenticated user attempting to access various endpoints
        $endpoints = [
            "/api/workstreams/{$this->workstream->id}",
            "/api/releases/{$this->release->id}/stakeholders",
            '/api/communications/search?query=secret',
        ];

        foreach ($endpoints as $url) {
            // When: Making unauthorized requests
            $response = $this->getJson($url);

            // Then: Response should not contain sensitive data
            $response->assertStatus(401);
            $response->assertJson(['message' => 'Unauthenticated.']);

            // Ensure no sensitive data is leaked in the response
            $responseData = $response->json();
            $this->assertArrayNotHasKey('data', $responseData);
            $this->assertArrayNotHasKey('workstreams', $responseData);
            $this->assertArrayNotHasKey('stakeholders', $responseData);
            $this->assertArrayNotHasKey('communications', $responseData);
        }
    }
}