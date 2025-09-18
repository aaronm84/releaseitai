<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class ConfigurationValidationTest extends TestCase
{
    /**
     * Test that app configuration has required keys
     *
     * @test
     */
    public function app_configuration_has_required_keys()
    {
        // Given: A Laravel application
        // When: Validating app configuration structure
        // Then: All required configuration keys should exist

        $requiredKeys = [
            'app.name',
            'app.env',
            'app.debug',
            'app.url',
            'app.timezone',
            'app.locale',
            'app.fallback_locale',
            'app.faker_locale',
            'app.key',
            'app.cipher',
            'app.providers',
            'app.aliases'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertConfigExists($key);
        }
    }

    /**
     * Test that database configuration has required structure
     *
     * @test
     */
    public function database_configuration_has_required_structure()
    {
        // Given: A Laravel application
        // When: Validating database configuration
        // Then: Database configuration should have proper structure

        $this->assertConfigExists('database.default');
        $this->assertConfigExists('database.connections');

        $connections = Config::get('database.connections');
        $this->assertIsArray($connections, 'Database connections should be an array');

        // Check that required connection types exist
        $requiredConnections = ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];
        foreach ($requiredConnections as $connection) {
            $this->assertArrayHasKey(
                $connection,
                $connections,
                "Database connection '{$connection}' should be configured"
            );
        }

        // Validate default connection exists
        $defaultConnection = Config::get('database.default');
        $this->assertArrayHasKey(
            $defaultConnection,
            $connections,
            "Default database connection '{$defaultConnection}' should exist in connections"
        );
    }

    /**
     * Test that auth configuration has required guards and providers
     *
     * @test
     */
    public function auth_configuration_has_required_guards_and_providers()
    {
        // Given: A Laravel application
        // When: Validating auth configuration
        // Then: Auth configuration should have proper structure

        $this->assertConfigExists('auth.defaults.guard');
        $this->assertConfigExists('auth.defaults.passwords');
        $this->assertConfigExists('auth.guards');
        $this->assertConfigExists('auth.providers');
        $this->assertConfigExists('auth.passwords');

        $guards = Config::get('auth.guards');
        $this->assertIsArray($guards, 'Auth guards should be an array');
        $this->assertArrayHasKey('web', $guards, 'Web guard should exist');
        $this->assertArrayHasKey('api', $guards, 'API guard should exist');

        $providers = Config::get('auth.providers');
        $this->assertIsArray($providers, 'Auth providers should be an array');
        $this->assertArrayHasKey('users', $providers, 'Users provider should exist');

        $passwords = Config::get('auth.passwords');
        $this->assertIsArray($passwords, 'Password reset configuration should be an array');
        $this->assertArrayHasKey('users', $passwords, 'Users password reset should exist');
    }

    /**
     * Test that cache configuration has required stores
     *
     * @test
     */
    public function cache_configuration_has_required_stores()
    {
        // Given: A Laravel application
        // When: Validating cache configuration
        // Then: Cache configuration should have proper stores

        $this->assertConfigExists('cache.default');
        $this->assertConfigExists('cache.stores');

        $stores = Config::get('cache.stores');
        $this->assertIsArray($stores, 'Cache stores should be an array');

        $requiredStores = ['array', 'database', 'file', 'redis'];
        foreach ($requiredStores as $store) {
            $this->assertArrayHasKey(
                $store,
                $stores,
                "Cache store '{$store}' should be configured"
            );
        }

        // Validate default store exists
        $defaultStore = Config::get('cache.default');
        $this->assertArrayHasKey(
            $defaultStore,
            $stores,
            "Default cache store '{$defaultStore}' should exist in stores"
        );
    }

    /**
     * Test that queue configuration has required connections
     *
     * @test
     */
    public function queue_configuration_has_required_connections()
    {
        // Given: A Laravel application
        // When: Validating queue configuration
        // Then: Queue configuration should have proper connections

        $this->assertConfigExists('queue.default');
        $this->assertConfigExists('queue.connections');

        $connections = Config::get('queue.connections');
        $this->assertIsArray($connections, 'Queue connections should be an array');

        $requiredConnections = ['sync', 'database', 'redis'];
        foreach ($requiredConnections as $connection) {
            $this->assertArrayHasKey(
                $connection,
                $connections,
                "Queue connection '{$connection}' should be configured"
            );
        }

        // Validate default connection exists
        $defaultConnection = Config::get('queue.default');
        $this->assertArrayHasKey(
            $defaultConnection,
            $connections,
            "Default queue connection '{$defaultConnection}' should exist in connections"
        );
    }

    /**
     * Test that session configuration has required settings
     *
     * @test
     */
    public function session_configuration_has_required_settings()
    {
        // Given: A Laravel application
        // When: Validating session configuration
        // Then: Session configuration should have security settings

        $requiredSessionKeys = [
            'session.driver',
            'session.lifetime',
            'session.expire_on_close',
            'session.encrypt',
            'session.files',
            'session.connection',
            'session.table',
            'session.store',
            'session.lottery',
            'session.cookie',
            'session.path',
            'session.domain',
            'session.secure',
            'session.http_only',
            'session.same_site'
        ];

        foreach ($requiredSessionKeys as $key) {
            $this->assertNotNull(
                Config::get($key),
                "Session configuration key '{$key}' should be set"
            );
        }

        // Validate security settings
        $this->assertTrue(
            Config::get('session.http_only'),
            'Sessions should be HTTP only for security'
        );

        $this->assertContains(
            Config::get('session.same_site'),
            ['lax', 'strict', 'none'],
            'Session same_site should be properly configured'
        );
    }

    /**
     * Test that mail configuration has required mailers
     *
     * @test
     */
    public function mail_configuration_has_required_mailers()
    {
        // Given: A Laravel application
        // When: Validating mail configuration
        // Then: Mail configuration should have proper mailers

        $this->assertConfigExists('mail.default');
        $this->assertConfigExists('mail.mailers');
        $this->assertConfigExists('mail.from.address');
        $this->assertConfigExists('mail.from.name');

        $mailers = Config::get('mail.mailers');
        $this->assertIsArray($mailers, 'Mail mailers should be an array');

        $requiredMailers = ['smtp', 'ses', 'postmark', 'sendmail', 'log', 'array', 'failover'];
        foreach ($requiredMailers as $mailer) {
            $this->assertArrayHasKey(
                $mailer,
                $mailers,
                "Mail mailer '{$mailer}' should be configured"
            );
        }

        // Validate default mailer exists
        $defaultMailer = Config::get('mail.default');
        $this->assertArrayHasKey(
            $defaultMailer,
            $mailers,
            "Default mail mailer '{$defaultMailer}' should exist in mailers"
        );
    }

    /**
     * Test that filesystem configuration has required disks
     *
     * @test
     */
    public function filesystem_configuration_has_required_disks()
    {
        // Given: A Laravel application
        // When: Validating filesystem configuration
        // Then: Filesystem configuration should have proper disks

        $this->assertConfigExists('filesystems.default');
        $this->assertConfigExists('filesystems.disks');

        $disks = Config::get('filesystems.disks');
        $this->assertIsArray($disks, 'Filesystem disks should be an array');

        $requiredDisks = ['local', 'public', 's3'];
        foreach ($requiredDisks as $disk) {
            $this->assertArrayHasKey(
                $disk,
                $disks,
                "Filesystem disk '{$disk}' should be configured"
            );
        }

        // Validate default disk exists
        $defaultDisk = Config::get('filesystems.default');
        $this->assertArrayHasKey(
            $defaultDisk,
            $disks,
            "Default filesystem disk '{$defaultDisk}' should exist in disks"
        );
    }

    /**
     * Test that logging configuration has required channels
     *
     * @test
     */
    public function logging_configuration_has_required_channels()
    {
        // Given: A Laravel application
        // When: Validating logging configuration
        // Then: Logging configuration should have proper channels

        $this->assertConfigExists('logging.default');
        $this->assertConfigExists('logging.channels');

        $channels = Config::get('logging.channels');
        $this->assertIsArray($channels, 'Logging channels should be an array');

        $requiredChannels = ['stack', 'single', 'daily', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog'];
        foreach ($requiredChannels as $channel) {
            $this->assertArrayHasKey(
                $channel,
                $channels,
                "Logging channel '{$channel}' should be configured"
            );
        }

        // Validate default channel exists
        $defaultChannel = Config::get('logging.default');
        $this->assertArrayHasKey(
            $defaultChannel,
            $channels,
            "Default logging channel '{$defaultChannel}' should exist in channels"
        );
    }

    /**
     * Test that configuration values are not using default/placeholder values
     *
     * @test
     */
    public function configuration_values_are_not_placeholders()
    {
        // Given: A Laravel application
        // When: Checking for placeholder values
        // Then: Configuration should not contain obvious placeholders

        $appKey = Config::get('app.key');
        $this->assertNotEmpty($appKey, 'App key should be generated');
        $this->assertNotEquals('base64:your-secret-key', $appKey, 'App key should not be placeholder');

        $appName = Config::get('app.name');
        $this->assertNotEquals('Laravel', $appName, 'App name should be customized for ReleaseIt');

        // Check database configuration is not using default values
        if (Config::get('database.default') !== 'sqlite') {
            $dbHost = Config::get('database.connections.' . Config::get('database.default') . '.host');
            $this->assertNotEquals('127.0.0.1', $dbHost, 'Database host should be configured for production');
        }
    }
}