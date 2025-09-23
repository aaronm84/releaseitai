<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\MagicLink;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class MagicLinkAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that magic link can be requested for existing user
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function magic_link_can_be_requested_for_existing_user(): void
    {
        // RED: This test should fail initially - we need to implement magic link request
        Mail::fake();

        // Given: An existing user
        $user = User::factory()->create(['email' => 'test@example.com']);

        // When: Requesting a magic link
        $response = $this->post('/magic-link/request', [
            'email' => 'test@example.com'
        ]);

        // Then: Should respond successfully
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Magic link sent to your email']);

        // And: A magic link should be created in database
        $this->assertDatabaseHas('magic_links', [
            'user_id' => $user->id,
            'email' => 'test@example.com'
        ]);

        // And: An email should be sent
        Mail::assertSent(\App\Mail\MagicLinkMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test that magic link can be requested for non-existing user
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function magic_link_can_be_requested_for_non_existing_user(): void
    {
        // RED: This test should fail - need to handle new user registration
        Mail::fake();

        // When: Requesting magic link for non-existing email
        $response = $this->post('/magic-link/request', [
            'email' => 'newuser@example.com'
        ]);

        // Then: Should respond successfully (security - don't leak if user exists)
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Magic link sent to your email']);

        // And: A new user should be created
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com'
        ]);

        // And: A magic link should be created
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertDatabaseHas('magic_links', [
            'user_id' => $user->id,
            'email' => 'newuser@example.com'
        ]);
    }

    /**
     * Test that magic link authentication works
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function magic_link_authentication_works(): void
    {
        // RED: This test should fail - need to implement authentication via magic link

        // Given: A user with a valid magic link
        $user = User::factory()->create();
        $magicLink = MagicLink::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => 'valid-token-123',
            'expires_at' => now()->addMinutes(15)
        ]);

        // When: Accessing the magic link
        $response = $this->get("/magic-link/verify/valid-token-123");

        // Then: Should be authenticated and redirected
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // And: Magic link should be consumed/deleted
        $this->assertDatabaseMissing('magic_links', [
            'token' => 'valid-token-123'
        ]);
    }

    /**
     * Test that expired magic links don't work
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function expired_magic_links_dont_work(): void
    {
        // RED: This test should fail - need to implement expiration validation

        // Given: A user with an expired magic link
        $user = User::factory()->create();
        $magicLink = MagicLink::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => 'expired-token-123',
            'expires_at' => now()->subMinutes(1) // Expired 1 minute ago
        ]);

        // When: Trying to use expired magic link
        $response = $this->get("/magic-link/verify/expired-token-123");

        // Then: Should be rejected
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Magic link has expired');
        $this->assertGuest();
    }

    /**
     * Test that invalid magic links don't work
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_magic_links_dont_work(): void
    {
        // RED: This test should fail - need to implement token validation

        // When: Trying to use non-existent magic link
        $response = $this->get("/magic-link/verify/invalid-token-123");

        // Then: Should be rejected
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Invalid magic link');
        $this->assertGuest();
    }

    /**
     * Test that magic links can only be used once
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function magic_links_can_only_be_used_once(): void
    {
        // RED: This test should fail - need to implement one-time use

        // Given: A user with a valid magic link
        $user = User::factory()->create();
        $magicLink = MagicLink::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => 'onetime-token-123',
            'expires_at' => now()->addMinutes(15)
        ]);

        // When: Using the magic link first time
        $this->get("/magic-link/verify/onetime-token-123");
        $this->assertAuthenticatedAs($user);

        // And: Logging out
        auth()->logout();

        // And: Trying to use the same magic link again
        $response = $this->get("/magic-link/verify/onetime-token-123");

        // Then: Should be rejected
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Invalid magic link');
        $this->assertGuest();
    }

    /**
     * Test magic link request validation
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function magic_link_request_requires_valid_email(): void
    {
        // RED: This test should fail - need to implement validation

        // When: Requesting magic link with invalid email
        $response = $this->post('/magic-link/request', [
            'email' => 'invalid-email'
        ]);

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Test magic link rate limiting
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function magic_link_requests_are_rate_limited(): void
    {
        // RED: This test should fail - need to implement rate limiting

        // Given: A user
        $user = User::factory()->create();

        // When: Making multiple magic link requests rapidly
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/magic-link/request', [
                'email' => $user->email
            ]);
        }

        // Then: Should be rate limited after 5 attempts
        $response->assertStatus(429); // Too Many Requests
    }
}