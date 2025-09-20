<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register_with_valid_data()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'password' => 'password123', // Password should be hashed
        ]);
    }

    /** @test */
    public function user_cannot_register_with_invalid_email()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-email',
        ]);
    }

    /** @test */
    public function user_cannot_register_with_existing_email()
    {
        $existingUser = User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'Jane Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertEquals(1, User::where('email', 'john@example.com')->count());
    }

    /** @test */
    public function user_cannot_register_with_mismatched_password_confirmation()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'email' => 'john@example.com',
        ]);
    }

    /** @test */
    public function user_cannot_register_with_short_password()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'email' => 'john@example.com',
        ]);
    }

    /** @test */
    public function user_can_register_with_optional_profile_fields()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'title' => 'Product Manager',
            'company' => 'TechCorp',
            'department' => 'Product',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'title',
                    'company',
                    'department',
                    'created_at',
                    'updated_at',
                ],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'title' => 'Product Manager',
            'company' => 'TechCorp',
            'department' => 'Product',
        ]);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
                'token'
            ]);

        // Verify token works by making an authenticated request
        $token = $response->json('token');
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $profileResponse->assertStatus(200);
        $this->assertEquals($user->email, $profileResponse->json('email'));
    }

    /** @test */
    public function user_cannot_login_with_invalid_email()
    {
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The provided credentials are incorrect.',
            ]);

        $this->assertGuest();
    }

    /** @test */
    public function user_cannot_login_with_invalid_password()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrong_password',
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The provided credentials are incorrect.',
            ]);

        $this->assertGuest();
    }

    /** @test */
    public function user_can_logout_successfully()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out',
            ]);

        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_cannot_logout_without_token()
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_get_profile_when_authenticated()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'title' => 'Product Manager',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'title',
                'company',
                'department',
                'created_at',
                'updated_at',
            ])
            ->assertJson([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'title' => 'Product Manager',
            ]);
    }

    /** @test */
    public function user_cannot_get_profile_without_authentication()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_update_profile_when_authenticated()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'title' => 'Developer',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $updateData = [
            'name' => 'John Smith',
            'title' => 'Senior Product Manager',
            'company' => 'TechCorp',
            'department' => 'Product',
            'phone' => '+1234567890',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/user', $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'title',
                'company',
                'department',
                'phone',
                'updated_at',
            ])
            ->assertJson([
                'name' => 'John Smith',
                'title' => 'Senior Product Manager',
                'company' => 'TechCorp',
                'department' => 'Product',
                'phone' => '+1234567890',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Smith',
            'title' => 'Senior Product Manager',
            'company' => 'TechCorp',
            'department' => 'Product',
            'phone' => '+1234567890',
        ]);
    }

    /** @test */
    public function user_cannot_update_email_to_existing_email()
    {
        $user1 = User::factory()->create(['email' => 'john@example.com']);
        $user2 = User::factory()->create(['email' => 'jane@example.com']);
        $token = $user1->createToken('auth_token')->plainTextToken;

        $updateData = [
            'email' => 'jane@example.com', // Existing email
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/user', $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'email' => 'john@example.com', // Email should remain unchanged
        ]);
    }

    /** @test */
    public function user_can_change_password_with_current_password()
    {
        $user = User::factory()->create([
            'password' => 'old_password123',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $passwordData = [
            'current_password' => 'old_password123',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/user/password', $passwordData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password updated successfully',
            ]);

        // Verify user can login with new password
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'new_password123',
        ]);

        $loginResponse->assertStatus(200);
    }

    /** @test */
    public function user_cannot_change_password_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => 'old_password123',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $passwordData = [
            'current_password' => 'wrong_password',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/user/password', $passwordData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Verify user still can login with old password
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'old_password123',
        ]);

        $loginResponse->assertStatus(200);
    }

    /** @test */
    public function user_gets_fresh_api_token_on_login()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        // Create an existing token
        $oldToken = $user->createToken('old_token')->plainTextToken;

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);

        $newToken = $response->json('token');

        // Verify the new token is different from the old token
        $this->assertNotEquals($oldToken, $newToken);

        // Verify the new token works
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
        ])->getJson('/api/user');

        $profileResponse->assertStatus(200);
    }
}