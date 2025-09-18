<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;
use App\Models\User;

class AuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that authentication configuration is properly set
     *
     * @test
     */
    public function authentication_configuration_is_set()
    {
        // Given: A Laravel application
        // When: Checking authentication configuration
        // Then: Authentication should be properly configured

        $authConfig = Config::get('auth');

        $this->assertArrayHasKey('defaults', $authConfig, 'Auth defaults should be configured');
        $this->assertArrayHasKey('guards', $authConfig, 'Auth guards should be configured');
        $this->assertArrayHasKey('providers', $authConfig, 'Auth providers should be configured');
        $this->assertArrayHasKey('passwords', $authConfig, 'Password reset should be configured');

        // Check default guard is web (session-based)
        $this->assertEquals('web', $authConfig['defaults']['guard'], 'Default guard should be web for session-based auth');

        // Check web guard uses session driver
        $this->assertArrayHasKey('web', $authConfig['guards'], 'Web guard should exist');
        $this->assertEquals('session', $authConfig['guards']['web']['driver'], 'Web guard should use session driver');

        // Check users provider uses eloquent
        $this->assertArrayHasKey('users', $authConfig['providers'], 'Users provider should exist');
        $this->assertEquals('eloquent', $authConfig['providers']['users']['driver'], 'Users provider should use eloquent driver');
    }

    /**
     * Test that session configuration supports authentication
     *
     * @test
     */
    public function session_configuration_supports_authentication()
    {
        // Given: A Laravel application
        // When: Checking session configuration
        // Then: Session should be properly configured for authentication

        $sessionConfig = Config::get('session');

        $this->assertNotEmpty($sessionConfig['driver'], 'Session driver should be configured');
        $this->assertNotEmpty($sessionConfig['lifetime'], 'Session lifetime should be configured');
        $this->assertTrue($sessionConfig['encrypt'], 'Sessions should be encrypted');
        $this->assertNotEmpty($sessionConfig['cookie'], 'Session cookie name should be configured');
        $this->assertTrue($sessionConfig['http_only'], 'Session cookies should be HTTP only');
        $this->assertTrue($sessionConfig['same_site'] !== null, 'Same site policy should be configured');
    }

    /**
     * Test that User model exists and is properly configured
     *
     * @test
     */
    public function user_model_exists_and_is_configured()
    {
        // Given: A Laravel application
        // When: Checking User model
        // Then: User model should exist and be properly configured

        $this->assertTrue(
            File::exists(app_path('Models/User.php')),
            'User model file should exist'
        );

        $this->assertTrue(
            class_exists('App\Models\User'),
            'User model class should exist'
        );

        // Check User model implements required contracts
        $user = new User();
        $this->assertInstanceOf(
            'Illuminate\Contracts\Auth\Authenticatable',
            $user,
            'User model should implement Authenticatable contract'
        );

        $this->assertInstanceOf(
            'Illuminate\Contracts\Auth\CanResetPassword',
            $user,
            'User model should implement CanResetPassword contract'
        );
    }

    /**
     * Test that User model has required attributes and methods
     *
     * @test
     */
    public function user_model_has_required_attributes()
    {
        // Given: A User model
        // When: Checking model attributes and methods
        // Then: Required attributes and methods should exist

        $user = new User();

        // Check fillable attributes
        $fillable = $user->getFillable();
        $requiredFillable = ['name', 'email', 'password'];

        foreach ($requiredFillable as $attribute) {
            $this->assertContains(
                $attribute,
                $fillable,
                "User model should have '{$attribute}' in fillable attributes"
            );
        }

        // Check hidden attributes
        $hidden = $user->getHidden();
        $requiredHidden = ['password', 'remember_token'];

        foreach ($requiredHidden as $attribute) {
            $this->assertContains(
                $attribute,
                $hidden,
                "User model should hide '{$attribute}' attribute"
            );
        }

        // Check casts
        $casts = $user->getCasts();
        $this->assertArrayHasKey('email_verified_at', $casts, 'email_verified_at should be cast');
        $this->assertArrayHasKey('password', $casts, 'password should be cast');
    }

    /**
     * Test that authentication guards are working
     *
     * @test
     */
    public function authentication_guards_are_working()
    {
        // Given: A Laravel application with authentication configured
        // When: Testing authentication guards
        // Then: Guards should be functional

        // Test web guard exists and is accessible
        $webGuard = Auth::guard('web');
        $this->assertNotNull($webGuard, 'Web guard should be accessible');

        // Test that guest status works
        $this->assertTrue(Auth::guest(), 'Should be guest when not authenticated');
        $this->assertFalse(Auth::check(), 'Should not be authenticated initially');

        // Test that user method returns null when not authenticated
        $this->assertNull(Auth::user(), 'Should return null when not authenticated');
    }

    /**
     * Test that password hashing is configured
     *
     * @test
     */
    public function password_hashing_is_configured()
    {
        // Given: A Laravel application
        // When: Testing password hashing
        // Then: Password hashing should work correctly

        $password = 'test-password-123';
        $hashedPassword = Hash::make($password);

        $this->assertNotEquals($password, $hashedPassword, 'Password should be hashed');
        $this->assertTrue(Hash::check($password, $hashedPassword), 'Hash check should work');
        $this->assertFalse(Hash::check('wrong-password', $hashedPassword), 'Wrong password should not match');
    }

    /**
     * Test that authentication middleware exists
     *
     * @test
     */
    public function authentication_middleware_exists()
    {
        // Given: A Laravel application
        // When: Checking authentication middleware
        // Then: Required middleware should be registered

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $routeMiddleware = $kernel->getRouteMiddleware();

        $requiredAuthMiddleware = [
            'auth',
            'guest',
            'verified'
        ];

        foreach ($requiredAuthMiddleware as $middleware) {
            $this->assertArrayHasKey(
                $middleware,
                $routeMiddleware,
                "Authentication middleware '{$middleware}' should be registered"
            );
        }
    }

    /**
     * Test that User factory can create users
     *
     * @test
     */
    public function user_factory_can_create_users()
    {
        // Given: A Laravel application with User factory
        // When: Creating a user with factory
        // Then: User should be created successfully

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);

        $this->assertInstanceOf(User::class, $user, 'Factory should create User instance');
        $this->assertEquals('test@example.com', $user->email, 'Email should be set correctly');
        $this->assertEquals('Test User', $user->name, 'Name should be set correctly');
        $this->assertNotEmpty($user->password, 'Password should be generated');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com'], 'User should be saved to database');
    }

    /**
     * Test that user can be authenticated via session
     *
     * @test
     */
    public function user_can_be_authenticated_via_session()
    {
        // Given: A user in the database
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // When: Attempting to authenticate
        $result = Auth::attempt([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Then: Authentication should succeed
        $this->assertTrue($result, 'Authentication should succeed with correct credentials');
        $this->assertTrue(Auth::check(), 'User should be authenticated');
        $this->assertEquals($user->id, Auth::id(), 'Authenticated user should match created user');
    }

    /**
     * Test that authentication fails with wrong credentials
     *
     * @test
     */
    public function authentication_fails_with_wrong_credentials()
    {
        // Given: A user in the database
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // When: Attempting to authenticate with wrong password
        $result = Auth::attempt([
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ]);

        // Then: Authentication should fail
        $this->assertFalse($result, 'Authentication should fail with wrong credentials');
        $this->assertFalse(Auth::check(), 'User should not be authenticated');
        $this->assertNull(Auth::user(), 'No user should be authenticated');
    }

    /**
     * Test that user can be logged out
     *
     * @test
     */
    public function user_can_be_logged_out()
    {
        // Given: An authenticated user
        $user = User::factory()->create();
        Auth::login($user);

        $this->assertTrue(Auth::check(), 'User should be authenticated initially');

        // When: Logging out
        Auth::logout();

        // Then: User should no longer be authenticated
        $this->assertFalse(Auth::check(), 'User should not be authenticated after logout');
        $this->assertNull(Auth::user(), 'No user should be authenticated after logout');
    }

    /**
     * Test that remember me functionality is available
     *
     * @test
     */
    public function remember_me_functionality_is_available()
    {
        // Given: A user in the database
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // When: Attempting to authenticate with remember option
        $result = Auth::attempt([
            'email' => 'test@example.com',
            'password' => 'password123'
        ], true);

        // Then: Authentication should succeed and remember token should be set
        $this->assertTrue($result, 'Authentication with remember should succeed');
        $this->assertTrue(Auth::check(), 'User should be authenticated');

        // Check that remember token is set in database
        $user->refresh();
        $this->assertNotNull($user->remember_token, 'Remember token should be set');
    }

    /**
     * Test that password reset configuration exists
     *
     * @test
     */
    public function password_reset_configuration_exists()
    {
        // Given: A Laravel application
        // When: Checking password reset configuration
        // Then: Password reset should be properly configured

        $passwordConfig = Config::get('auth.passwords.users');

        $this->assertNotNull($passwordConfig, 'Password reset configuration should exist');
        $this->assertEquals('users', $passwordConfig['provider'], 'Password reset should use users provider');
        $this->assertArrayHasKey('table', $passwordConfig, 'Password reset table should be configured');
        $this->assertArrayHasKey('expire', $passwordConfig, 'Password reset expiration should be configured');
        $this->assertArrayHasKey('throttle', $passwordConfig, 'Password reset throttling should be configured');
    }
}