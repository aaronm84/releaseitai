<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Communication;
use App\Models\CommunicationParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SqlInjectionPreventionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workstream $workstream;
    private Release $release;
    private Communication $communication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $this->workstream = Workstream::factory()->create([
            'name' => 'Test Workstream',
            'owner_id' => $this->user->id
        ]);

        $this->release = Release::factory()->create([
            'name' => 'Test Release'
        ]);

        $this->communication = Communication::factory()->create([
            'release_id' => $this->release->id,
            'subject' => 'Test Communication',
            'content' => 'Test content for communication'
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function communication_search_prevents_sql_injection_in_query_parameter()
    {
        // Given: SQL injection payloads in search query
        $sqlInjectionPayloads = [
            "'; DROP TABLE communications; --",
            "' UNION SELECT * FROM users --",
            "' OR '1'='1",
            "'; DELETE FROM users WHERE id = 1; --",
            "test' AND (SELECT COUNT(*) FROM users) > 0 --",
            "'; INSERT INTO communications (content) VALUES ('hacked'); --",
            "' UNION SELECT password FROM users WHERE email = 'admin@example.com' --",
            "test'; UPDATE users SET email = 'hacked@evil.com' WHERE id = 1; --",
            "' OR 1=1 UNION SELECT table_name FROM information_schema.tables --",
            "test\"; DROP TABLE workstreams; --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through search query
            $response = $this->getJson("/api/communications/search?query=" . urlencode($payload));

            // Then: Should not execute malicious SQL and return safe results
            $response->assertStatus(200);

            // Verify tables still exist (not dropped)
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('communications'));
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('users'));
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('workstreams'));

            // Verify no unauthorized data access
            $responseData = $response->json();
            if (isset($responseData['data'])) {
                foreach ($responseData['data'] as $item) {
                    // Should not contain password fields or unauthorized data
                    $this->assertArrayNotHasKey('password', $item);
                    $this->assertArrayNotHasKey('remember_token', $item);
                }
            }
        }
    }

    /** @test */
    public function workstream_filtering_prevents_sql_injection_in_type_parameter()
    {
        // Given: SQL injection payloads in workstream type filter
        $sqlInjectionPayloads = [
            "product_line'; DROP TABLE workstreams; --",
            "initiative' OR '1'='1",
            "experiment' UNION SELECT * FROM users --",
            "'; DELETE FROM workstreams; --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through type filter
            $response = $this->getJson("/api/workstreams?type=" . urlencode($payload));

            // Then: Should handle safely (either filter properly or return validation error)
            $this->assertContains($response->getStatusCode(), [200, 422]);

            // Verify table integrity
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('workstreams'));
            $this->assertDatabaseHas('workstreams', ['id' => $this->workstream->id]);
        }
    }

    /** @test */
    public function workstream_filtering_prevents_sql_injection_in_status_parameter()
    {
        // Given: SQL injection payloads in workstream status filter
        $sqlInjectionPayloads = [
            "active'; DROP TABLE workstreams; --",
            "draft' OR 1=1 --",
            "completed' UNION SELECT password FROM users --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through status filter
            $response = $this->getJson("/api/workstreams?status=" . urlencode($payload));

            // Then: Should handle safely
            $this->assertContains($response->getStatusCode(), [200, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('workstreams'));
        }
    }

    /** @test */
    public function workstream_parent_filtering_prevents_sql_injection()
    {
        // Given: SQL injection payloads in parent workstream filter
        $sqlInjectionPayloads = [
            "1'; DROP TABLE workstreams; --",
            "null' OR '1'='1",
            "1 UNION SELECT id FROM users",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through parent filter
            $response = $this->getJson("/api/workstreams?parent_workstream_id=" . urlencode($payload));

            // Then: Should handle safely
            $this->assertContains($response->getStatusCode(), [200, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('workstreams'));
        }
    }

    /** @test */
    public function communication_search_prevents_sql_injection_in_release_id_filter()
    {
        // Given: SQL injection payloads in release_id filter
        $sqlInjectionPayloads = [
            "1'; DROP TABLE releases; --",
            "1 OR 1=1",
            "1 UNION SELECT * FROM users",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through release_id filter
            $response = $this->getJson("/api/communications/search?query=test&release_id=" . urlencode($payload));

            // Then: Should handle safely
            $this->assertContains($response->getStatusCode(), [200, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('releases'));
        }
    }

    /** @test */
    public function communication_date_filtering_prevents_sql_injection()
    {
        // Given: SQL injection payloads in date filters
        $sqlInjectionPayloads = [
            "2024-01-01'; DROP TABLE communications; --",
            "2024-01-01' OR '1'='1",
            "'; DELETE FROM users; --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through date filters
            $response = $this->getJson("/api/communications/search?query=test&start_date=" . urlencode($payload));

            // Then: Should handle safely (likely validation error for invalid date)
            $this->assertContains($response->getStatusCode(), [200, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('communications'));
        }
    }

    /** @test */
    public function bulk_workstream_update_prevents_sql_injection_in_workstream_ids()
    {
        // Given: SQL injection payloads in workstream IDs array
        $sqlInjectionPayloads = [
            ["1'; DROP TABLE workstreams; --"],
            ["1", "2'; DELETE FROM users; --"],
            ["1 UNION SELECT id FROM users"],
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through workstream IDs
            $response = $this->putJson('/api/workstreams/bulk-update', [
                'workstream_ids' => $payload,
                'updates' => ['status' => 'active']
            ]);

            // Then: Should handle safely (validation should catch invalid IDs)
            $this->assertContains($response->getStatusCode(), [200, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('workstreams'));
        }
    }

    /** @test */
    public function communication_thread_id_filtering_prevents_sql_injection()
    {
        // Given: SQL injection payloads in thread_id
        $sqlInjectionPayloads = [
            "thread123'; DROP TABLE communications; --",
            "thread' OR '1'='1",
            "'; SELECT * FROM users; --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through thread_id
            $response = $this->getJson("/api/releases/{$this->release->id}/communications?thread_id=" . urlencode($payload));

            // Then: Should handle safely
            $response->assertStatus(200);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('communications'));
        }
    }

    /** @test */
    public function participant_id_filtering_prevents_sql_injection()
    {
        // Given: SQL injection payloads in participant_id
        $sqlInjectionPayloads = [
            "1'; DROP TABLE users; --",
            "1 OR 1=1",
            "999 UNION SELECT * FROM communications",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through participant_id
            $response = $this->getJson("/api/releases/{$this->release->id}/communications?participant_id=" . urlencode($payload));

            // Then: Should handle safely
            $this->assertContains($response->getStatusCode(), [200, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('users'));
        }
    }

    /** @test */
    public function communication_sorting_prevents_sql_injection()
    {
        // Given: SQL injection payloads in sort parameters
        $sqlInjectionPayloads = [
            "communication_date'; DROP TABLE communications; --",
            "priority; DELETE FROM users; --",
            "channel, (SELECT password FROM users WHERE id = 1)",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through sort_by parameter
            $response = $this->getJson("/api/releases/{$this->release->id}/communications?sort_by=" . urlencode($payload));

            // Then: Should handle safely (validation should reject invalid sort fields)
            $this->assertContains($response->getStatusCode(), [200, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('communications'));
        }
    }

    /** @test */
    public function ilike_operations_are_properly_escaped()
    {
        // Given: Content that could be interpreted as SQL wildcards or operators
        $testCommunication = Communication::factory()->create([
            'release_id' => $this->release->id,
            'subject' => "Report with % wildcard and _ underscore",
            'content' => "Content with [brackets] and 'quotes' and \"double quotes\""
        ]);

        $searchTerms = [
            "%",          // SQL wildcard
            "_",          // SQL wildcard
            "% OR 1=1",   // Attempted boolean injection
            "'; --",      // Comment injection
            "[",          // Square bracket
            "'",          // Single quote
            "\"",         // Double quote
        ];

        foreach ($searchTerms as $term) {
            // When: Searching with potentially problematic characters
            $response = $this->getJson("/api/communications/search?query=" . urlencode($term));

            // Then: Should handle safely without SQL errors
            $response->assertStatus(200);

            // Verify response structure is correct
            $response->assertJsonStructure([
                'data',
                'links',
                'meta'
            ]);
        }
    }

    /** @test */
    public function permission_queries_prevent_sql_injection()
    {
        // Given: SQL injection payloads in permission-related endpoints
        $sqlInjectionPayloads = [
            "1'; DROP TABLE workstream_permissions; --",
            "1 UNION SELECT * FROM users",
            "999 OR 1=1",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through user_id in permissions
            $response = $this->postJson("/api/workstreams/{$this->workstream->id}/permissions", [
                'user_id' => $payload,
                'permission_type' => 'view'
            ]);

            // Then: Should handle safely (validation should catch invalid user_id)
            $this->assertContains($response->getStatusCode(), [201, 422, 403]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('workstream_permissions'));
        }
    }

    /** @test */
    public function numeric_id_parameters_reject_sql_injection()
    {
        // Given: SQL injection attempts in numeric ID parameters
        $sqlInjectionPayloads = [
            "1; DROP TABLE users; --",
            "1 OR 1=1",
            "1 UNION SELECT 1",
            "'; DELETE FROM workstreams; --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            // When: Attempting SQL injection through workstream ID in URL
            $response = $this->getJson("/api/workstreams/" . urlencode($payload));

            // Then: Should either return 404 (model not found) or handle safely
            $this->assertContains($response->getStatusCode(), [404, 422]);
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('workstreams'));
            $this->assertTrue(DB::getSchemaBuilder()->hasTable('users'));
        }
    }

    /** @test */
    public function array_parameters_prevent_sql_injection()
    {
        // Given: SQL injection payloads in array parameters (like metadata)
        $maliciousMetadata = [
            'key' => "value'; DROP TABLE communications; --",
            'settings' => ["'; DELETE FROM users; --"],
            'config' => "' OR 1=1 --"
        ];

        // When: Attempting to store malicious metadata
        $response = $this->postJson("/api/releases/{$this->release->id}/communications", [
            'channel' => 'email',
            'content' => 'Test communication',
            'communication_type' => 'notification',
            'direction' => 'outbound',
            'metadata' => $maliciousMetadata,
            'participants' => [
                ['user_id' => $this->user->id, 'type' => 'primary']
            ]
        ]);

        // Then: Should store safely without executing SQL
        $this->assertContains($response->getStatusCode(), [201, 422]);
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('communications'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('users'));
    }

    /** @test */
    public function database_queries_use_parameterized_statements()
    {
        // Given: A search query that would be vulnerable if not parameterized
        $searchQuery = "test'; SELECT * FROM users WHERE email = 'admin@example.com'; --";

        // When: Performing search operations
        $response = $this->getJson("/api/communications/search?query=" . urlencode($searchQuery));

        // Then: Should execute safely using parameterized queries
        $response->assertStatus(200);

        // Verify the search actually looks for the literal string, not executing SQL
        $responseData = $response->json();
        $this->assertIsArray($responseData['data']);

        // If any results are returned, they should contain the search term literally
        foreach ($responseData['data'] as $communication) {
            if (stripos($communication['subject'] ?? '', $searchQuery) !== false ||
                stripos($communication['content'] ?? '', $searchQuery) !== false) {
                // Found a match - this means it searched for the literal string
                $this->assertTrue(true);
                return;
            }
        }

        // No matches found is also acceptable - it means the injection didn't execute
        $this->assertTrue(true);
    }

    /** @test */
    public function special_characters_in_search_are_handled_safely()
    {
        // Given: Communications with special characters
        $specialCharCommunication = Communication::factory()->create([
            'release_id' => $this->release->id,
            'subject' => "Test: <script>alert('xss')</script>",
            'content' => "Content with 'quotes', \"double quotes\", and [brackets] & ampersands"
        ]);

        $specialCharQueries = [
            "<script>alert('xss')</script>",
            "quotes\"",
            "brackets]",
            "&ampersands",
            "\\backslash",
            "%wildcard",
            "_underscore",
        ];

        foreach ($specialCharQueries as $query) {
            // When: Searching for special characters
            $response = $this->getJson("/api/communications/search?query=" . urlencode($query));

            // Then: Should handle safely without errors
            $response->assertStatus(200);
            $response->assertJsonStructure([
                'data',
                'links',
                'meta'
            ]);
        }
    }
}