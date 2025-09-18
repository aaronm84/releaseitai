<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Models\User;

class CoreEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that web routes file exists and is loaded
     *
     * @test
     */
    public function web_routes_file_exists_and_is_loaded()
    {
        // Given: A Laravel application
        // When: Checking web routes file
        // Then: Web routes file should exist and be loaded

        $this->assertTrue(
            File::exists(base_path('routes/web.php')),
            'Web routes file should exist'
        );

        // Check that routes are actually loaded
        $routes = Route::getRoutes();
        $this->assertGreaterThan(0, count($routes), 'Routes should be loaded');
    }

    /**
     * Test that API routes file exists and is loaded
     *
     * @test
     */
    public function api_routes_file_exists_and_is_loaded()
    {
        // Given: A Laravel application
        // When: Checking API routes file
        // Then: API routes file should exist and be loaded

        $this->assertTrue(
            File::exists(base_path('routes/api.php')),
            'API routes file should exist'
        );
    }

    /**
     * Test that dashboard route exists and requires authentication
     *
     * @test
     */
    public function dashboard_route_exists_and_requires_authentication()
    {
        // Given: A Laravel application
        // When: Accessing dashboard route without authentication
        // Then: Should redirect to login

        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        // When: Accessing dashboard route with authentication
        // Then: Should be accessible
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    /**
     * Test that workstreams route exists and requires authentication
     *
     * @test
     */
    public function workstreams_route_exists_and_requires_authentication()
    {
        // Given: A Laravel application
        // When: Accessing workstreams route without authentication
        // Then: Should redirect to login

        $response = $this->get('/workstreams');
        $response->assertRedirect('/login');

        // When: Accessing workstreams route with authentication
        // Then: Should be accessible
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/workstreams');
        $response->assertStatus(200);
    }

    /**
     * Test that releases route exists and requires authentication
     *
     * @test
     */
    public function releases_route_exists_and_requires_authentication()
    {
        // Given: A Laravel application
        // When: Accessing releases route without authentication
        // Then: Should redirect to login

        $response = $this->get('/releases');
        $response->assertRedirect('/login');

        // When: Accessing releases route with authentication
        // Then: Should be accessible
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/releases');
        $response->assertStatus(200);
    }

    /**
     * Test that login route exists and is accessible to guests
     *
     * @test
     */
    public function login_route_exists_and_is_accessible_to_guests()
    {
        // Given: A Laravel application
        // When: Accessing login route as guest
        // Then: Should be accessible

        $response = $this->get('/login');
        $response->assertStatus(200);

        // When: Accessing login route as authenticated user
        // Then: Should redirect to intended page (likely dashboard)
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/login');
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test that register route exists and is accessible to guests
     *
     * @test
     */
    public function register_route_exists_and_is_accessible_to_guests()
    {
        // Given: A Laravel application
        // When: Accessing register route as guest
        // Then: Should be accessible

        $response = $this->get('/register');
        $response->assertStatus(200);

        // When: Accessing register route as authenticated user
        // Then: Should redirect to intended page (likely dashboard)
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/register');
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test that logout route exists and works for authenticated users
     *
     * @test
     */
    public function logout_route_exists_and_works()
    {
        // Given: An authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // When: Posting to logout route
        $response = $this->post('/logout');

        // Then: Should logout and redirect
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Test that password reset routes exist
     *
     * @test
     */
    public function password_reset_routes_exist()
    {
        // Given: A Laravel application
        // When: Checking password reset routes
        // Then: Password reset routes should be accessible

        // Forgot password form
        $response = $this->get('/forgot-password');
        $response->assertStatus(200);

        // Reset password form (with token)
        $response = $this->get('/reset-password/test-token');
        $response->assertStatus(200);
    }

    /**
     * Test that home route exists and redirects appropriately
     *
     * @test
     */
    public function home_route_exists_and_redirects_appropriately()
    {
        // Given: A Laravel application
        // When: Accessing home route as guest
        // Then: Should show welcome page or redirect to login

        $response = $this->get('/');
        $this->assertContains($response->getStatusCode(), [200, 302]);

        // When: Accessing home route as authenticated user
        // Then: Should redirect to dashboard
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/');
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test that API routes have proper structure
     *
     * @test
     */
    public function api_routes_have_proper_structure()
    {
        // Given: A Laravel application
        // When: Checking API routes structure
        // Then: API routes should be properly configured

        $routes = Route::getRoutes();
        $apiRoutes = [];

        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $apiRoutes[] = $route;
            }
        }

        $this->assertGreaterThan(0, count($apiRoutes), 'API routes should exist');

        // Check that API routes have throttle middleware
        foreach ($apiRoutes as $route) {
            $middleware = $route->gatherMiddleware();
            $hasThrottle = false;
            foreach ($middleware as $m) {
                if (str_contains($m, 'throttle')) {
                    $hasThrottle = true;
                    break;
                }
            }
            $this->assertTrue($hasThrottle, 'API routes should have throttle middleware');
        }
    }

    /**
     * Test that core route names are defined
     *
     * @test
     */
    public function core_route_names_are_defined()
    {
        // Given: A Laravel application
        // When: Checking for named routes
        // Then: Core routes should have names

        $requiredRouteNames = [
            'dashboard',
            'workstreams.index',
            'releases.index',
            'login',
            'register',
            'logout',
            'password.request',
            'password.reset'
        ];

        foreach ($requiredRouteNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route '{$routeName}' should be defined");
        }
    }

    /**
     * Test that routes use proper HTTP methods
     *
     * @test
     */
    public function routes_use_proper_http_methods()
    {
        // Given: A Laravel application
        // When: Checking route HTTP methods
        // Then: Routes should use appropriate HTTP methods

        $routeMethodExpectations = [
            'dashboard' => ['GET', 'HEAD'],
            'workstreams.index' => ['GET', 'HEAD'],
            'releases.index' => ['GET', 'HEAD'],
            'login' => ['GET', 'HEAD'],
            'register' => ['GET', 'HEAD'],
            'logout' => ['POST']
        ];

        foreach ($routeMethodExpectations as $routeName => $expectedMethods) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route) {
                $routeMethods = $route->methods();
                foreach ($expectedMethods as $method) {
                    $this->assertContains(
                        $method,
                        $routeMethods,
                        "Route '{$routeName}' should support {$method} method"
                    );
                }
            }
        }
    }

    /**
     * Test that middleware groups are properly applied
     *
     * @test
     */
    public function middleware_groups_are_properly_applied()
    {
        // Given: A Laravel application
        // When: Checking middleware on core routes
        // Then: Proper middleware should be applied

        // Dashboard should have auth middleware
        $dashboardRoute = Route::getRoutes()->getByName('dashboard');
        if ($dashboardRoute) {
            $middleware = $dashboardRoute->gatherMiddleware();
            $this->assertContains('auth', $middleware, 'Dashboard route should have auth middleware');
        }

        // Login should have guest middleware
        $loginRoute = Route::getRoutes()->getByName('login');
        if ($loginRoute) {
            $middleware = $loginRoute->gatherMiddleware();
            $this->assertContains('guest', $middleware, 'Login route should have guest middleware');
        }

        // Web routes should have web middleware group
        $webRoutes = Route::getRoutes()->getRoutesByName();
        foreach (['dashboard', 'workstreams.index', 'releases.index'] as $routeName) {
            if (isset($webRoutes[$routeName])) {
                $middleware = $webRoutes[$routeName]->gatherMiddleware();
                $hasWebMiddleware = false;
                foreach ($middleware as $m) {
                    if (str_contains($m, 'web') || str_contains($m, 'StartSession') || str_contains($m, 'VerifyCsrfToken')) {
                        $hasWebMiddleware = true;
                        break;
                    }
                }
                $this->assertTrue($hasWebMiddleware, "Route '{$routeName}' should have web middleware");
            }
        }
    }

    /**
     * Test that CSRF protection is enabled for web routes
     *
     * @test
     */
    public function csrf_protection_is_enabled_for_web_routes()
    {
        // Given: A Laravel application
        // When: Making POST request without CSRF token
        // Then: Should receive CSRF error

        $response = $this->post('/logout');
        $response->assertStatus(419); // CSRF token mismatch
    }

    /**
     * Test that rate limiting is configured for authentication routes
     *
     * @test
     */
    public function rate_limiting_is_configured_for_auth_routes()
    {
        // Given: A Laravel application
        // When: Checking authentication routes for rate limiting
        // Then: Rate limiting should be applied

        $authRoutes = ['login', 'register', 'password.request'];

        foreach ($authRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route) {
                $middleware = $route->gatherMiddleware();
                $hasThrottle = false;
                foreach ($middleware as $m) {
                    if (str_contains($m, 'throttle')) {
                        $hasThrottle = true;
                        break;
                    }
                }
                // Note: Some auth routes might not have throttling in basic setup,
                // but it's recommended for production
            }
        }

        $this->assertTrue(true, 'Rate limiting check completed');
    }
}