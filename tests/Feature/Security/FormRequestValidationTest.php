<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Communication;
use App\Models\CommunicationParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Workstream $workstream;
    private Workstream $parentWorkstream;
    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->parentWorkstream = Workstream::factory()->create([
            'owner_id' => $this->user->id,
            'type' => 'product_line'
        ]);

        $this->workstream = Workstream::factory()->create([
            'owner_id' => $this->user->id,
            'parent_workstream_id' => $this->parentWorkstream->id,
            'type' => 'initiative'
        ]);

        $this->release = Release::factory()->create();

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function store_workstream_request_validates_required_fields()
    {
        // Given: Missing required fields for workstream creation
        $invalidData = [
            // Missing name
            [],
            // Missing type
            ['name' => 'Test Workstream'],
            // Missing owner_id
            ['name' => 'Test Workstream', 'type' => 'product_line'],
        ];

        foreach ($invalidData as $data) {
            // When: Attempting to create workstream with invalid data
            $response = $this->postJson('/api/workstreams', $data);

            // Then: Should return 422 validation error
            $response->assertStatus(422);
            $response->assertJsonStructure([
                'message',
                'errors'
            ]);

            // Verify specific validation errors
            $errors = $response->json('errors');
            if (!isset($data['name'])) {
                $this->assertArrayHasKey('name', $errors);
            }
            if (!isset($data['type'])) {
                $this->assertArrayHasKey('type', $errors);
            }
            if (!isset($data['owner_id'])) {
                $this->assertArrayHasKey('owner_id', $errors);
            }
        }
    }

    /** @test */
    public function store_workstream_request_validates_type_field()
    {
        // Given: Invalid workstream types
        $invalidTypes = [
            'invalid_type',
            'project',  // Not in allowed enum
            'task',     // Not in allowed enum
            123,        // Not a string
            ['product_line'], // Array instead of string
            ''          // Empty string
        ];

        foreach ($invalidTypes as $type) {
            // When: Attempting to create workstream with invalid type
            $response = $this->postJson('/api/workstreams', [
                'name' => 'Test Workstream',
                'type' => $type,
                'owner_id' => $this->user->id
            ]);

            // Then: Should return 422 validation error
            $response->assertStatus(422);
            $response->assertJsonPath('errors.type', function ($errors) {
                return is_array($errors) && count($errors) > 0;
            });
        }
    }

    /** @test */
    public function store_workstream_request_validates_owner_exists()
    {
        // Given: Non-existent owner IDs
        $invalidOwnerIds = [
            999999,     // Non-existent user
            'invalid',  // String instead of integer
            null,       // Null value
            []          // Array instead of integer
        ];

        foreach ($invalidOwnerIds as $ownerId) {
            // When: Attempting to create workstream with invalid owner
            $response = $this->postJson('/api/workstreams', [
                'name' => 'Test Workstream',
                'type' => 'product_line',
                'owner_id' => $ownerId
            ]);

            // Then: Should return 422 validation error
            $response->assertStatus(422);
            $response->assertJsonPath('errors.owner_id', function ($errors) {
                return is_array($errors) && count($errors) > 0;
            });
        }
    }

    /** @test */
    public function store_workstream_request_validates_parent_workstream_exists()
    {
        // Given: Invalid parent workstream IDs
        $response = $this->postJson('/api/workstreams', [
            'name' => 'Test Workstream',
            'type' => 'initiative',
            'owner_id' => $this->user->id,
            'parent_workstream_id' => 999999  // Non-existent workstream
        ]);

        // Then: Should return 422 validation error
        $response->assertStatus(422);
        $response->assertJsonPath('errors.parent_workstream_id', function ($errors) {
            return is_array($errors) && count($errors) > 0;
        });
    }

    /** @test */
    public function store_workstream_request_validates_hierarchy_depth()
    {
        // Given: A workstream at maximum depth (3 levels)
        $level2Workstream = Workstream::factory()->create([
            'owner_id' => $this->user->id,
            'parent_workstream_id' => $this->workstream->id,
            'type' => 'experiment'
        ]);

        // When: Attempting to create a 4th level workstream
        $response = $this->postJson('/api/workstreams', [
            'name' => 'Deep Workstream',
            'type' => 'experiment',
            'owner_id' => $this->user->id,
            'parent_workstream_id' => $level2Workstream->id
        ]);

        // Then: Should return 422 validation error for hierarchy depth
        $response->assertStatus(422);
        $response->assertJsonPath('errors.parent_workstream_id', function ($errors) {
            return is_array($errors) &&
                   count($errors) > 0 &&
                   str_contains(implode(' ', $errors), 'hierarchy cannot exceed 3 levels');
        });
    }

    /** @test */
    public function store_workstream_request_validates_name_length()
    {
        // Given: Workstream name that exceeds maximum length
        $longName = str_repeat('a', 256); // Exceeds 255 character limit

        // When: Attempting to create workstream with overly long name
        $response = $this->postJson('/api/workstreams', [
            'name' => $longName,
            'type' => 'product_line',
            'owner_id' => $this->user->id
        ]);

        // Then: Should return 422 validation error
        $response->assertStatus(422);
        $response->assertJsonPath('errors.name', function ($errors) {
            return is_array($errors) && count($errors) > 0;
        });
    }

    /** @test */
    public function update_workstream_request_allows_partial_updates()
    {
        // Given: A valid workstream update with only some fields
        $validPartialUpdates = [
            ['name' => 'Updated Name'],
            ['description' => 'Updated description'],
            ['status' => 'completed'],
            ['type' => 'experiment']
        ];

        foreach ($validPartialUpdates as $updateData) {
            // When: Updating workstream with partial data
            $response = $this->putJson("/api/workstreams/{$this->workstream->id}", $updateData);

            // Then: Should be successful or return authorization error (not validation error)
            $this->assertContains($response->getStatusCode(), [200, 403]);
            if ($response->getStatusCode() === 422) {
                $this->fail("Partial update should not fail validation: " . json_encode($response->json()));
            }
        }
    }

    /** @test */
    public function communication_store_request_validates_required_fields()
    {
        // Given: Invalid communication data missing required fields
        $invalidData = [
            // Missing channel
            [
                'content' => 'Test content',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'participants' => [['user_id' => $this->user->id]]
            ],
            // Missing content
            [
                'channel' => 'email',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'participants' => [['user_id' => $this->user->id]]
            ],
            // Missing communication_type
            [
                'channel' => 'email',
                'content' => 'Test content',
                'direction' => 'outbound',
                'participants' => [['user_id' => $this->user->id]]
            ],
            // Missing direction
            [
                'channel' => 'email',
                'content' => 'Test content',
                'communication_type' => 'notification',
                'participants' => [['user_id' => $this->user->id]]
            ],
            // Missing participants
            [
                'channel' => 'email',
                'content' => 'Test content',
                'communication_type' => 'notification',
                'direction' => 'outbound'
            ]
        ];

        foreach ($invalidData as $data) {
            // When: Attempting to create communication with invalid data
            $response = $this->postJson("/api/releases/{$this->release->id}/communications", $data);

            // Then: Should return 422 validation error
            $response->assertStatus(422);
            $response->assertJsonStructure(['message', 'errors']);
        }
    }

    /** @test */
    public function communication_store_request_validates_enum_fields()
    {
        // Given: Invalid enum values for communication
        $invalidEnumData = [
            // Invalid channel
            [
                'channel' => 'invalid_channel',
                'content' => 'Test',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'participants' => [['user_id' => $this->user->id]]
            ],
            // Invalid communication_type
            [
                'channel' => 'email',
                'content' => 'Test',
                'communication_type' => 'invalid_type',
                'direction' => 'outbound',
                'participants' => [['user_id' => $this->user->id]]
            ],
            // Invalid direction
            [
                'channel' => 'email',
                'content' => 'Test',
                'communication_type' => 'notification',
                'direction' => 'invalid_direction',
                'participants' => [['user_id' => $this->user->id]]
            ],
            // Invalid priority
            [
                'channel' => 'email',
                'content' => 'Test',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'priority' => 'invalid_priority',
                'participants' => [['user_id' => $this->user->id]]
            ]
        ];

        foreach ($invalidEnumData as $data) {
            // When: Attempting to create communication with invalid enum values
            $response = $this->postJson("/api/releases/{$this->release->id}/communications", $data);

            // Then: Should return 422 validation error
            $response->assertStatus(422);
            $response->assertJsonStructure(['message', 'errors']);
        }
    }

    /** @test */
    public function communication_store_request_validates_participants_array()
    {
        // Given: Invalid participant data
        $invalidParticipantData = [
            // Empty participants array
            [
                'channel' => 'email',
                'content' => 'Test',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'participants' => []
            ],
            // Participant without user_id
            [
                'channel' => 'email',
                'content' => 'Test',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'participants' => [['type' => 'primary']]
            ],
            // Participant with non-existent user_id
            [
                'channel' => 'email',
                'content' => 'Test',
                'communication_type' => 'notification',
                'direction' => 'outbound',
                'participants' => [['user_id' => 999999]]
            ]
        ];

        foreach ($invalidParticipantData as $data) {
            // When: Attempting to create communication with invalid participants
            $response = $this->postJson("/api/releases/{$this->release->id}/communications", $data);

            // Then: Should return 422 validation error
            $response->assertStatus(422);
            $response->assertJsonPath('errors.participants', function ($errors) {
                return !empty($errors);
            });
        }
    }

    /** @test */
    public function communication_date_filtering_validates_date_format()
    {
        // Given: Invalid date formats
        $invalidDates = [
            'invalid-date',
            '2024-13-01',    // Invalid month
            '2024-02-30',    // Invalid day
            '24-01-01',      // Wrong format
            'yesterday',     // Text instead of date
            '2024/01/01'     // Wrong separator
        ];

        foreach ($invalidDates as $date) {
            // When: Using invalid date in filtering
            $response = $this->getJson("/api/releases/{$this->release->id}/communications?start_date={$date}");

            // Then: Should return validation error
            $response->assertStatus(422);
            $response->assertJsonPath('errors.start_date', function ($errors) {
                return is_array($errors) && count($errors) > 0;
            });
        }
    }

    /** @test */
    public function communication_date_range_validation_ensures_end_after_start()
    {
        // Given: End date before start date
        $startDate = '2024-01-15';
        $endDate = '2024-01-10';

        // When: Using invalid date range
        $response = $this->getJson("/api/releases/{$this->release->id}/communications?start_date={$startDate}&end_date={$endDate}");

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonPath('errors.end_date', function ($errors) {
            return is_array($errors) && count($errors) > 0;
        });
    }

    /** @test */
    public function communication_pagination_validates_per_page_limits()
    {
        // Given: Invalid per_page values
        $invalidPerPageValues = [
            0,      // Too small
            101,    // Too large
            -1,     // Negative
            'abc'   // Non-numeric
        ];

        foreach ($invalidPerPageValues as $perPage) {
            // When: Using invalid per_page value
            $response = $this->getJson("/api/releases/{$this->release->id}/communications?per_page={$perPage}");

            // Then: Should return validation error
            $response->assertStatus(422);
            $response->assertJsonPath('errors.per_page', function ($errors) {
                return is_array($errors) && count($errors) > 0;
            });
        }
    }

    /** @test */
    public function communication_sorting_validates_sort_fields()
    {
        // Given: Invalid sort_by values
        $invalidSortFields = [
            'invalid_field',
            'user_password',  // Potentially sensitive field
            'id; DROP TABLE', // SQL injection attempt
            ''               // Empty string
        ];

        foreach ($invalidSortFields as $sortField) {
            // When: Using invalid sort field
            $response = $this->getJson("/api/releases/{$this->release->id}/communications?sort_by={$sortField}");

            // Then: Should return validation error
            $response->assertStatus(422);
            $response->assertJsonPath('errors.sort_by', function ($errors) {
                return is_array($errors) && count($errors) > 0;
            });
        }
    }

    /** @test */
    public function communication_search_validates_minimum_query_length()
    {
        // Given: Search query that's too short
        $shortQueries = ['a', 'ab', ''];

        foreach ($shortQueries as $query) {
            // When: Searching with too short query
            $response = $this->getJson("/api/communications/search?query={$query}");

            // Then: Should return validation error
            $response->assertStatus(422);
            $response->assertJsonPath('errors.query', function ($errors) {
                return is_array($errors) && count($errors) > 0;
            });
        }
    }

    /** @test */
    public function workstream_permissions_request_validates_required_fields()
    {
        // Given: Invalid permission data
        $invalidPermissionData = [
            // Missing user_id
            ['permission_type' => 'view'],
            // Missing permission_type
            ['user_id' => $this->otherUser->id],
            // Invalid permission_type
            ['user_id' => $this->otherUser->id, 'permission_type' => 'invalid'],
            // Invalid user_id
            ['user_id' => 999999, 'permission_type' => 'view'],
            // Invalid scope
            ['user_id' => $this->otherUser->id, 'permission_type' => 'view', 'scope' => 'invalid_scope']
        ];

        foreach ($invalidPermissionData as $data) {
            // When: Attempting to grant invalid permissions
            $response = $this->postJson("/api/workstreams/{$this->workstream->id}/permissions", $data);

            // Then: Should return validation error
            $response->assertStatus(422);
            $response->assertJsonStructure(['message', 'errors']);
        }
    }

    /** @test */
    public function workstream_move_request_validates_parent_exists()
    {
        // Given: Non-existent parent workstream ID
        $response = $this->putJson("/api/workstreams/{$this->workstream->id}/move", [
            'new_parent_workstream_id' => 999999
        ]);

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonPath('errors.new_parent_workstream_id', function ($errors) {
            return is_array($errors) && count($errors) > 0;
        });
    }

    /** @test */
    public function bulk_update_validates_workstream_ids_exist()
    {
        // Given: Non-existent workstream IDs
        $response = $this->putJson('/api/workstreams/bulk-update', [
            'workstream_ids' => [999999, 999998],
            'updates' => ['status' => 'completed']
        ]);

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonPath('errors.workstream_ids.0', function ($errors) {
            return is_array($errors) && count($errors) > 0;
        });
    }

    /** @test */
    public function validation_error_responses_have_consistent_format()
    {
        // Given: Invalid workstream data
        $response = $this->postJson('/api/workstreams', [
            'name' => '',  // Invalid: required field
            'type' => 'invalid_type',  // Invalid: not in enum
            'owner_id' => 999999  // Invalid: user doesn't exist
        ]);

        // Then: Should return consistent validation error format
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'name',
                'type',
                'owner_id'
            ]
        ]);

        // Verify error message format
        $errors = $response->json('errors');
        foreach ($errors as $field => $fieldErrors) {
            $this->assertIsArray($fieldErrors);
            $this->assertNotEmpty($fieldErrors);
            foreach ($fieldErrors as $error) {
                $this->assertIsString($error);
                $this->assertNotEmpty($error);
            }
        }
    }

    /** @test */
    public function validation_prevents_script_injection_in_text_fields()
    {
        // Given: Potentially malicious content in text fields
        $maliciousData = [
            'name' => '<script>alert("xss")</script>',
            'description' => 'javascript:alert("xss")',
            'type' => 'product_line',
            'owner_id' => $this->user->id
        ];

        // When: Submitting data with potential XSS
        $response = $this->postJson('/api/workstreams', $maliciousData);

        // Then: Should either accept and sanitize, or reject based on validation rules
        // The key is that it shouldn't execute the script
        $this->assertContains($response->getStatusCode(), [201, 422]);

        if ($response->getStatusCode() === 201) {
            // If accepted, verify the data is safely stored
            $workstream = Workstream::latest()->first();
            $this->assertStringContainsString('&lt;script&gt;', $workstream->name);
        }
    }
}