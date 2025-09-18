<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;

class LaravelApplicationStructureTest extends TestCase
{
    /**
     * Test that Laravel application is properly bootstrapped
     *
     * @test
     */
    public function laravel_application_boots_successfully()
    {
        // Given: A Laravel application environment
        // When: The application is booted
        // Then: The application should be available and running Laravel 11.x

        $this->assertTrue(App::isBooted(), 'Laravel application should be booted');
        $this->assertStringStartsWith('11.', app()->version(), 'Should be running Laravel 11.x');
    }

    /**
     * Test that essential Laravel directories exist
     *
     * @test
     */
    public function essential_directories_exist()
    {
        // Given: A Laravel application structure
        // When: Checking for essential directories
        // Then: All required directories should exist

        $requiredDirectories = [
            'app',
            'app/Http',
            'app/Http/Controllers',
            'app/Http/Middleware',
            'app/Models',
            'app/Providers',
            'bootstrap',
            'config',
            'database',
            'database/migrations',
            'database/seeders',
            'public',
            'resources',
            'resources/views',
            'routes',
            'storage',
            'storage/app',
            'storage/framework',
            'storage/logs',
            'tests',
            'tests/Feature',
            'tests/Unit'
        ];

        foreach ($requiredDirectories as $directory) {
            $this->assertTrue(
                File::isDirectory(base_path($directory)),
                "Directory '{$directory}' should exist"
            );
        }
    }

    /**
     * Test that essential Laravel files exist
     *
     * @test
     */
    public function essential_files_exist()
    {
        // Given: A Laravel application
        // When: Checking for essential files
        // Then: All required files should exist

        $requiredFiles = [
            'artisan',
            'composer.json',
            'package.json',
            '.env',
            '.env.example',
            'app/Http/Kernel.php',
            'app/Providers/AppServiceProvider.php',
            'app/Providers/AuthServiceProvider.php',
            'app/Providers/EventServiceProvider.php',
            'app/Providers/RouteServiceProvider.php',
            'bootstrap/app.php',
            'config/app.php',
            'config/auth.php',
            'config/cache.php',
            'config/database.php',
            'config/filesystems.php',
            'config/queue.php',
            'config/session.php',
            'routes/web.php',
            'routes/api.php',
            'public/index.php'
        ];

        foreach ($requiredFiles as $file) {
            $this->assertTrue(
                File::exists(base_path($file)),
                "File '{$file}' should exist"
            );
        }
    }

    /**
     * Test that application configuration is properly set
     *
     * @test
     */
    public function application_configuration_is_set()
    {
        // Given: A Laravel application
        // When: Checking application configuration
        // Then: Essential configuration should be set

        $this->assertNotEmpty(Config::get('app.name'), 'App name should be configured');
        $this->assertNotEmpty(Config::get('app.key'), 'App key should be generated');
        $this->assertContains(Config::get('app.env'), ['local', 'testing', 'staging', 'production'], 'App environment should be valid');
        $this->assertIsBool(Config::get('app.debug'), 'Debug mode should be boolean');
        $this->assertNotEmpty(Config::get('app.url'), 'App URL should be configured');
        $this->assertEquals('UTC', Config::get('app.timezone'), 'Timezone should be UTC by default');
        $this->assertEquals('en', Config::get('app.locale'), 'Default locale should be English');
    }

    /**
     * Test that service providers are properly registered
     *
     * @test
     */
    public function service_providers_are_registered()
    {
        // Given: A Laravel application
        // When: Checking registered service providers
        // Then: Essential service providers should be registered

        $registeredProviders = app()->getLoadedProviders();

        $requiredProviders = [
            'Illuminate\Auth\AuthServiceProvider',
            'Illuminate\Broadcasting\BroadcastServiceProvider',
            'Illuminate\Bus\BusServiceProvider',
            'Illuminate\Cache\CacheServiceProvider',
            'Illuminate\Foundation\Providers\ConsoleSupportServiceProvider',
            'Illuminate\Cookie\CookieServiceProvider',
            'Illuminate\Database\DatabaseServiceProvider',
            'Illuminate\Encryption\EncryptionServiceProvider',
            'Illuminate\Filesystem\FilesystemServiceProvider',
            'Illuminate\Foundation\Providers\FoundationServiceProvider',
            'Illuminate\Hashing\HashServiceProvider',
            'Illuminate\Mail\MailServiceProvider',
            'Illuminate\Notifications\NotificationServiceProvider',
            'Illuminate\Pagination\PaginationServiceProvider',
            'Illuminate\Pipeline\PipelineServiceProvider',
            'Illuminate\Queue\QueueServiceProvider',
            'Illuminate\Redis\RedisServiceProvider',
            'Illuminate\Auth\Passwords\PasswordResetServiceProvider',
            'Illuminate\Session\SessionServiceProvider',
            'Illuminate\Translation\TranslationServiceProvider',
            'Illuminate\Validation\ValidationServiceProvider',
            'Illuminate\View\ViewServiceProvider',
            'App\Providers\AppServiceProvider',
            'App\Providers\AuthServiceProvider',
            'App\Providers\EventServiceProvider',
            'App\Providers\RouteServiceProvider'
        ];

        foreach ($requiredProviders as $provider) {
            $this->assertArrayHasKey(
                $provider,
                $registeredProviders,
                "Service provider '{$provider}' should be registered"
            );
        }
    }

    /**
     * Test that middleware is properly configured
     *
     * @test
     */
    public function middleware_is_configured()
    {
        // Given: A Laravel application
        // When: Checking middleware configuration
        // Then: Essential middleware should be configured

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);

        // Check global middleware
        $globalMiddleware = $kernel->getGlobalMiddleware();
        $this->assertNotEmpty($globalMiddleware, 'Global middleware should be configured');

        // Check middleware groups
        $middlewareGroups = $kernel->getMiddlewareGroups();
        $this->assertArrayHasKey('web', $middlewareGroups, 'Web middleware group should exist');
        $this->assertArrayHasKey('api', $middlewareGroups, 'API middleware group should exist');

        // Check route middleware
        $routeMiddleware = $kernel->getRouteMiddleware();
        $requiredRouteMiddleware = ['auth', 'guest', 'verified', 'throttle'];

        foreach ($requiredRouteMiddleware as $middleware) {
            $this->assertArrayHasKey(
                $middleware,
                $routeMiddleware,
                "Route middleware '{$middleware}' should be registered"
            );
        }
    }

    /**
     * Test that Composer dependencies are properly installed
     *
     * @test
     */
    public function composer_dependencies_are_installed()
    {
        // Given: A Laravel application
        // When: Checking Composer dependencies
        // Then: Vendor directory and autoloader should exist

        $this->assertTrue(
            File::isDirectory(base_path('vendor')),
            'Vendor directory should exist'
        );

        $this->assertTrue(
            File::exists(base_path('vendor/autoload.php')),
            'Composer autoloader should exist'
        );

        $this->assertTrue(
            File::exists(base_path('composer.lock')),
            'Composer lock file should exist'
        );
    }

    /**
     * Test that storage directories are writable
     *
     * @test
     */
    public function storage_directories_are_writable()
    {
        // Given: A Laravel application
        // When: Checking storage directory permissions
        // Then: Storage directories should be writable

        $storageDirectories = [
            'storage/app',
            'storage/framework',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs'
        ];

        foreach ($storageDirectories as $directory) {
            $fullPath = base_path($directory);

            $this->assertTrue(
                File::isDirectory($fullPath),
                "Storage directory '{$directory}' should exist"
            );

            $this->assertTrue(
                is_writable($fullPath),
                "Storage directory '{$directory}' should be writable"
            );
        }
    }
}