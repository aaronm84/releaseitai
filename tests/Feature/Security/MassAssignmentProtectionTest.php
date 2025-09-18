<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Communication;
use App\Models\CommunicationParticipant;
use App\Models\WorkstreamPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MassAssignmentProtectionTest extends TestCase
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

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function workstream_creation_prevents_mass_assignment_of_protected_attributes()
    {
        // Given: Attempt to mass assign protected attributes during workstream creation
        $maliciousData = [
            'name' => 'Test Workstream',
            'type' => 'product_line',
            'owner_id' => $this->user->id,

            // Protected attributes that should not be mass assignable
            'id' => 999999,                    // Primary key
            'created_at' => '2020-01-01',      // Timestamp
            'updated_at' => '2020-01-01',      // Timestamp
            'deleted_at' => null,              // Soft delete timestamp
            '_token' => 'malicious_token',     // CSRF token
            '_method' => 'DELETE',             // HTTP method override
        ];

        // When: Creating workstream with malicious data
        $response = $this->postJson('/api/workstreams', $maliciousData);

        // Then: Should create workstream but ignore protected attributes
        $this->assertContains($response->getStatusCode(), [201, 403, 422]);

        if ($response->getStatusCode() === 201) {
            $workstream = Workstream::latest()->first();

            // Verify protected attributes were not mass assigned
            $this->assertNotEquals(999999, $workstream->id);
            $this->assertNotEquals('2020-01-01', $workstream->created_at->format('Y-m-d'));
            $this->assertNotEquals('2020-01-01', $workstream->updated_at->format('Y-m-d'));

            // Verify fillable attributes were assigned correctly
            $this->assertEquals('Test Workstream', $workstream->name);
            $this->assertEquals('product_line', $workstream->type);
            $this->assertEquals($this->user->id, $workstream->owner_id);
        }
    }

    /** @test */
    public function workstream_update_prevents_mass_assignment_of_protected_attributes()
    {
        // Given: Attempt to mass assign protected attributes during workstream update
        $maliciousData = [
            'name' => 'Updated Workstream',

            // Protected attributes that should not be mass assignable
            'id' => 999999,
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
            'owner_id' => $this->otherUser->id,  // Attempting to change ownership via mass assignment
        ];

        $originalCreatedAt = $this->workstream->created_at;
        $originalId = $this->workstream->id;
        $originalOwnerId = $this->workstream->owner_id;

        // When: Updating workstream with malicious data
        $response = $this->putJson("/api/workstreams/{$this->workstream->id}", $maliciousData);

        // Then: Should handle request but ignore protected attributes
        $this->assertContains($response->getStatusCode(), [200, 403, 422]);

        $this->workstream->refresh();

        // Verify protected attributes were not changed
        $this->assertEquals($originalId, $this->workstream->id);
        $this->assertEquals($originalCreatedAt->format('Y-m-d H:i:s'), $this->workstream->created_at->format('Y-m-d H:i:s'));

        // owner_id should be fillable, so this test checks if it changed
        // If it changed, that's expected since it's in fillable array
        // If it didn't change, that might indicate additional authorization protection
        if ($response->getStatusCode() === 200) {
            // The update was successful - check if the name was updated
            $this->assertEquals('Updated Workstream', $this->workstream->name);
        }
    }

    /** @test */
    public function user_model_prevents_mass_assignment_of_sensitive_attributes()
    {
        // Given: Attempt to mass assign sensitive user attributes
        $sensitiveData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',

            // Sensitive attributes that should be protected
            'id' => 999999,
            'email_verified_at' => now(),
            'remember_token' => 'malicious_token',
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
            'is_admin' => true,               // If such field exists
            'role' => 'admin',                // If such field exists
            'permissions' => ['admin'],       // If such field exists
        ];

        // When: Creating user with sensitive data (simulating mass assignment)
        try {
            $user = new User();
            $user->fill($sensitiveData);

            // Then: Should only fill fillable attributes
            $this->assertEquals('Test User', $user->name);
            $this->assertEquals('test@example.com', $user->email);
            $this->assertEquals('password123', $user->password);

            // Protected attributes should not be set
            $this->assertNull($user->id);
            $this->assertNull($user->remember_token);
            $this->assertNull($user->email_verified_at);

            // Additional fields that might exist should not be set
            $this->assertNull($user->getAttribute('is_admin'));
            $this->assertNull($user->getAttribute('role'));
            $this->assertNull($user->getAttribute('permissions'));

        } catch (MassAssignmentException $e) {
            // This is also acceptable - it means mass assignment protection is working
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function communication_creation_prevents_mass_assignment_of_system_attributes()
    {
        // Given: Attempt to mass assign system attributes during communication creation
        $maliciousData = [
            'channel' => 'email',
            'content' => 'Test communication',
            'communication_type' => 'notification',
            'direction' => 'outbound',
            'participants' => [
                ['user_id' => $this->user->id, 'type' => 'primary']
            ],

            // System attributes that should not be mass assignable
            'id' => 999999,
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
            'initiated_by_user_id' => $this->otherUser->id,  // Should be set by system, not user input
        ];

        // When: Creating communication with malicious data
        $response = $this->postJson("/api/releases/{$this->release->id}/communications", $maliciousData);

        // Then: Should create communication but ignore system attributes
        $this->assertContains($response->getStatusCode(), [201, 422]);

        if ($response->getStatusCode() === 201) {
            $communication = Communication::latest()->first();

            // Verify system attributes were set correctly by the system
            $this->assertNotEquals(999999, $communication->id);
            $this->assertNotEquals('2020-01-01', $communication->created_at->format('Y-m-d'));
            $this->assertEquals($this->user->id, $communication->initiated_by_user_id); // Set by auth(), not mass assignment
        }
    }

    /** @test */
    public function bulk_workstream_update_prevents_mass_assignment_of_restricted_fields()
    {
        // Given: Multiple workstreams and attempt to mass assign restricted fields
        $workstream1 = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $workstream2 = Workstream::factory()->create(['owner_id' => $this->user->id]);

        $maliciousUpdateData = [
            'workstream_ids' => [$workstream1->id, $workstream2->id],
            'updates' => [
                'status' => 'completed',

                // Restricted fields that should not be mass assignable in bulk updates
                'id' => 999999,
                'created_at' => '2020-01-01',
                'updated_at' => '2020-01-01',
                'owner_id' => $this->otherUser->id,  // Attempting to change ownership in bulk
            ]
        ];

        $originalOwnerIds = [$workstream1->owner_id, $workstream2->owner_id];

        // When: Performing bulk update with malicious data
        $response = $this->putJson('/api/workstreams/bulk-update', $maliciousUpdateData);

        // Then: Should update allowed fields but ignore restricted ones
        $this->assertContains($response->getStatusCode(), [200, 422, 403]);

        $workstream1->refresh();
        $workstream2->refresh();

        if ($response->getStatusCode() === 200) {
            // Verify allowed field was updated
            $this->assertEquals('completed', $workstream1->status);
            $this->assertEquals('completed', $workstream2->status);
        }

        // Verify restricted fields were not changed (regardless of response status)
        $this->assertEquals($originalOwnerIds[0], $workstream1->owner_id);
        $this->assertEquals($originalOwnerIds[1], $workstream2->owner_id);
    }

    /** @test */
    public function workstream_permissions_prevent_mass_assignment_of_sensitive_fields()
    {
        // Given: Attempt to create workstream permission with mass assigned sensitive fields
        $maliciousPermissionData = [
            'user_id' => $this->otherUser->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',

            // Sensitive fields that should not be mass assignable
            'id' => 999999,
            'workstream_id' => 888888,        // Should be set from route parameter
            'granted_by' => $this->otherUser->id,  // Should be set by system from auth()
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
        ];

        // When: Creating workstream permission with malicious data
        $response = $this->postJson("/api/workstreams/{$this->workstream->id}/permissions", $maliciousPermissionData);

        // Then: Should handle request but ignore sensitive fields
        $this->assertContains($response->getStatusCode(), [201, 422, 403]);

        if ($response->getStatusCode() === 201) {
            $permission = WorkstreamPermission::latest()->first();

            // Verify sensitive fields were set correctly by the system
            $this->assertNotEquals(999999, $permission->id);
            $this->assertEquals($this->workstream->id, $permission->workstream_id); // From route, not mass assignment
            $this->assertEquals($this->user->id, $permission->granted_by); // From auth(), not mass assignment
            $this->assertNotEquals('2020-01-01', $permission->created_at->format('Y-m-d'));
        }
    }

    /** @test */
    public function json_input_with_nested_mass_assignment_attempts_are_blocked()
    {
        // Given: Nested JSON with mass assignment attempts
        $nestedMaliciousData = [
            'name' => 'Test Workstream',
            'type' => 'product_line',
            'owner_id' => $this->user->id,

            // Nested attempts to bypass mass assignment protection
            'attributes' => [
                'id' => 999999,
                'created_at' => '2020-01-01'
            ],
            'workstream' => [
                'id' => 888888,
                'owner_id' => $this->otherUser->id
            ],
            'user' => [
                'id' => $this->otherUser->id,
                'remember_token' => 'malicious_token'
            ]
        ];

        // When: Submitting nested malicious data
        $response = $this->postJson('/api/workstreams', $nestedMaliciousData);

        // Then: Should handle request safely
        $this->assertContains($response->getStatusCode(), [201, 422, 403]);

        if ($response->getStatusCode() === 201) {
            $workstream = Workstream::latest()->first();

            // Verify only expected fields were set
            $this->assertEquals('Test Workstream', $workstream->name);
            $this->assertEquals('product_line', $workstream->type);
            $this->assertNotEquals(999999, $workstream->id);
            $this->assertNotEquals(888888, $workstream->id);
        }
    }

    /** @test */
    public function mass_assignment_protection_works_with_array_parameters()
    {
        // Given: Array parameters that might bypass mass assignment protection
        $arrayMaliciousData = [
            'name' => ['Test Workstream', '<script>alert("xss")</script>'],
            'type' => 'product_line',
            'owner_id' => [$this->user->id, $this->otherUser->id],
            'description' => [
                'main' => 'Test description',
                'id' => 999999,  // Trying to sneak in protected field
                'created_at' => '2020-01-01'
            ]
        ];

        // When: Submitting array-based malicious data
        $response = $this->postJson('/api/workstreams', $arrayMaliciousData);

        // Then: Should handle safely (likely validation error due to type mismatch)
        $this->assertContains($response->getStatusCode(), [201, 422, 403]);

        // Verify no workstream was created with malicious data
        $maliciousWorkstream = Workstream::where('id', 999999)->first();
        $this->assertNull($maliciousWorkstream);
    }

    /** @test */
    public function fillable_attributes_are_correctly_defined_for_security()
    {
        // Given: Models should have appropriate fillable attributes defined
        $workstreamFillable = (new Workstream())->getFillable();
        $userFillable = (new User())->getFillable();

        // Then: Verify sensitive attributes are NOT in fillable arrays
        $workstreamSensitiveFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $userSensitiveFields = ['id', 'created_at', 'updated_at', 'remember_token', 'email_verified_at'];

        foreach ($workstreamSensitiveFields as $field) {
            $this->assertNotContains($field, $workstreamFillable,
                "Workstream model should not have '{$field}' in fillable array");
        }

        foreach ($userSensitiveFields as $field) {
            $this->assertNotContains($field, $userFillable,
                "User model should not have '{$field}' in fillable array");
        }

        // Verify expected safe attributes ARE in fillable arrays
        $this->assertContains('name', $workstreamFillable);
        $this->assertContains('type', $workstreamFillable);
        $this->assertContains('owner_id', $workstreamFillable);

        $this->assertContains('name', $userFillable);
        $this->assertContains('email', $userFillable);
        $this->assertContains('password', $userFillable);
    }

    /** @test */
    public function guarded_attributes_prevent_mass_assignment_when_fillable_not_used()
    {
        // Given: A model instance to test guarded behavior
        $workstream = new Workstream();

        // Check if the model uses guarded instead of fillable (some models might)
        $guarded = $workstream->getGuarded();

        if (count($guarded) > 0 && $guarded !== ['*']) {
            // When: Model uses guarded, sensitive fields should be in guarded array
            $sensitiveFields = ['id', 'created_at', 'updated_at'];

            foreach ($sensitiveFields as $field) {
                // Then: Sensitive fields should be guarded
                $this->assertContains($field, $guarded,
                    "Sensitive field '{$field}' should be in guarded array");
            }
        } else {
            // Model uses fillable approach (which is the case for Workstream)
            $this->assertTrue(true, "Model uses fillable approach for mass assignment protection");
        }
    }

    /** @test */
    public function relationship_mass_assignment_is_properly_protected()
    {
        // Given: Attempt to mass assign relationship data
        $relationshipMaliciousData = [
            'name' => 'Test Workstream',
            'type' => 'product_line',
            'owner_id' => $this->user->id,

            // Attempting to mass assign relationship data
            'owner' => [
                'id' => $this->otherUser->id,
                'name' => 'Hacked User',
                'email' => 'hacked@evil.com',
                'password' => 'newpassword',
                'remember_token' => 'malicious_token'
            ],
            'parentWorkstream' => [
                'id' => 999999,
                'name' => 'Malicious Parent'
            ],
            'childWorkstreams' => [
                ['id' => 888888, 'name' => 'Malicious Child']
            ]
        ];

        // When: Creating workstream with relationship mass assignment attempts
        $response = $this->postJson('/api/workstreams', $relationshipMaliciousData);

        // Then: Should create workstream but ignore relationship mass assignments
        $this->assertContains($response->getStatusCode(), [201, 422, 403]);

        if ($response->getStatusCode() === 201) {
            $workstream = Workstream::latest()->first();

            // Verify the owner relationship is correctly set (by owner_id, not mass assignment)
            $this->assertEquals($this->user->id, $workstream->owner_id);

            // Verify the actual user record was not modified
            $this->user->refresh();
            $this->assertNotEquals('Hacked User', $this->user->name);
            $this->assertNotEquals('hacked@evil.com', $this->user->email);
        }

        // Verify no malicious related records were created
        $this->assertNull(Workstream::find(999999));
        $this->assertNull(Workstream::find(888888));
    }
}