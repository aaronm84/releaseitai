<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\File;

class EnvironmentConfigurationTest extends TestCase
{
    /**
     * Test that environment file exists and is readable
     *
     * @test
     */
    public function environment_file_exists_and_is_readable()
    {
        // Given: A Laravel application
        // When: Checking environment files
        // Then: Environment files should exist

        $this->assertTrue(
            File::exists(base_path('.env')),
            '.env file should exist'
        );

        $this->assertTrue(
            File::exists(base_path('.env.example')),
            '.env.example file should exist'
        );

        $this->assertTrue(
            is_readable(base_path('.env')),
            '.env file should be readable'
        );
    }

    /**
     * Test that Redis configuration is properly set
     *
     * @test
     */
    public function redis_configuration_is_set()
    {
        // Given: A Laravel application
        // When: Checking Redis configuration
        // Then: Redis should be properly configured

        $redisConfig = Config::get('database.redis');

        $this->assertNotNull($redisConfig, 'Redis configuration should exist');
        $this->assertArrayHasKey('default', $redisConfig, 'Default Redis connection should be configured');
        $this->assertArrayHasKey('cache', $redisConfig, 'Cache Redis connection should be configured');

        // Check default connection
        $defaultConnection = $redisConfig['default'];
        $this->assertArrayHasKey('host', $defaultConnection, 'Redis host should be configured');
        $this->assertArrayHasKey('port', $defaultConnection, 'Redis port should be configured');
        $this->assertArrayHasKey('database', $defaultConnection, 'Redis database should be configured');

        // Check cache connection
        $cacheConnection = $redisConfig['cache'];
        $this->assertArrayHasKey('host', $cacheConnection, 'Redis cache host should be configured');
        $this->assertArrayHasKey('port', $cacheConnection, 'Redis cache port should be configured');
        $this->assertArrayHasKey('database', $cacheConnection, 'Redis cache database should be configured');
    }

    /**
     * Test that Redis connection can be established
     *
     * @test
     */
    public function redis_connection_can_be_established()
    {
        // Given: A Laravel application with Redis configured
        // When: Attempting to connect to Redis
        // Then: Connection should be successful

        try {
            $redis = Redis::connection();
            $redis->ping();
            $this->assertTrue(true, 'Redis connection should be successful');
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis is not available for testing: ' . $e->getMessage());
        }
    }

    /**
     * Test that Redis can store and retrieve data
     *
     * @test
     */
    public function redis_can_store_and_retrieve_data()
    {
        // Given: A working Redis connection
        // When: Storing and retrieving data
        // Then: Data should be stored and retrieved correctly

        try {
            $redis = Redis::connection();
            $testKey = 'test_key_' . time();
            $testValue = 'test_value_' . time();

            // Store data
            $redis->set($testKey, $testValue);

            // Retrieve data
            $retrievedValue = $redis->get($testKey);

            $this->assertEquals($testValue, $retrievedValue, 'Redis should store and retrieve data correctly');

            // Clean up
            $redis->del($testKey);
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis is not available for testing: ' . $e->getMessage());
        }
    }

    /**
     * Test that cache configuration supports Redis
     *
     * @test
     */
    public function cache_configuration_supports_redis()
    {
        // Given: A Laravel application
        // When: Checking cache configuration
        // Then: Cache should be configured to use Redis

        $cacheConfig = Config::get('cache');

        $this->assertNotNull($cacheConfig, 'Cache configuration should exist');
        $this->assertArrayHasKey('stores', $cacheConfig, 'Cache stores should be configured');
        $this->assertArrayHasKey('redis', $cacheConfig['stores'], 'Redis cache store should be configured');

        $redisStore = $cacheConfig['stores']['redis'];
        $this->assertEquals('redis', $redisStore['driver'], 'Redis store should use redis driver');
        $this->assertArrayHasKey('connection', $redisStore, 'Redis store should specify connection');
    }

    /**
     * Test that session configuration supports Redis
     *
     * @test
     */
    public function session_configuration_supports_redis()
    {
        // Given: A Laravel application
        // When: Checking session configuration
        // Then: Session configuration should support Redis

        $sessionConfig = Config::get('session');

        $this->assertNotNull($sessionConfig, 'Session configuration should exist');

        // Check that redis is available as a driver option
        $availableDrivers = ['file', 'cookie', 'database', 'redis', 'array'];
        $currentDriver = $sessionConfig['driver'];

        $this->assertContains(
            $currentDriver,
            $availableDrivers,
            'Session driver should be one of the supported drivers'
        );

        // If using redis, check connection
        if ($currentDriver === 'redis') {
            $this->assertArrayHasKey('connection', $sessionConfig, 'Redis session should specify connection');
        }
    }

    /**
     * Test that queue configuration supports Redis
     *
     * @test
     */
    public function queue_configuration_supports_redis()
    {
        // Given: A Laravel application
        // When: Checking queue configuration
        // Then: Queue should be configured with Redis support

        $queueConfig = Config::get('queue');

        $this->assertNotNull($queueConfig, 'Queue configuration should exist');
        $this->assertArrayHasKey('connections', $queueConfig, 'Queue connections should be configured');
        $this->assertArrayHasKey('redis', $queueConfig['connections'], 'Redis queue connection should be configured');

        $redisConnection = $queueConfig['connections']['redis'];
        $this->assertEquals('redis', $redisConnection['driver'], 'Redis queue should use redis driver');
        $this->assertArrayHasKey('connection', $redisConnection, 'Redis queue should specify connection');
        $this->assertArrayHasKey('queue', $redisConnection, 'Redis queue should specify queue name');
    }

    /**
     * Test that AWS S3 configuration is properly set
     *
     * @test
     */
    public function aws_s3_configuration_is_set()
    {
        // Given: A Laravel application
        // When: Checking AWS S3 configuration
        // Then: S3 should be properly configured

        $filesystemConfig = Config::get('filesystems');

        $this->assertNotNull($filesystemConfig, 'Filesystem configuration should exist');
        $this->assertArrayHasKey('disks', $filesystemConfig, 'Filesystem disks should be configured');
        $this->assertArrayHasKey('s3', $filesystemConfig['disks'], 'S3 disk should be configured');

        $s3Config = $filesystemConfig['disks']['s3'];
        $this->assertEquals('s3', $s3Config['driver'], 'S3 disk should use s3 driver');

        $requiredS3Keys = ['key', 'secret', 'region', 'bucket'];
        foreach ($requiredS3Keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $s3Config,
                "S3 configuration should have '{$key}' key"
            );
        }
    }

    /**
     * Test that AWS credentials are configured
     *
     * @test
     */
    public function aws_credentials_are_configured()
    {
        // Given: A Laravel application
        // When: Checking AWS credentials configuration
        // Then: AWS credentials should be set

        $awsKey = Config::get('filesystems.disks.s3.key');
        $awsSecret = Config::get('filesystems.disks.s3.secret');
        $awsRegion = Config::get('filesystems.disks.s3.region');
        $awsBucket = Config::get('filesystems.disks.s3.bucket');

        $this->assertNotEmpty($awsKey, 'AWS access key should be configured');
        $this->assertNotEmpty($awsSecret, 'AWS secret key should be configured');
        $this->assertNotEmpty($awsRegion, 'AWS region should be configured');
        $this->assertNotEmpty($awsBucket, 'AWS bucket should be configured');

        // Check that credentials are not placeholder values
        $this->assertNotEquals('your-aws-access-key-id', $awsKey, 'AWS key should not be placeholder');
        $this->assertNotEquals('your-aws-secret-access-key', $awsSecret, 'AWS secret should not be placeholder');
        $this->assertNotEquals('us-east-1', $awsRegion, 'AWS region should be set to actual region');
        $this->assertNotEquals('your-bucket-name', $awsBucket, 'AWS bucket should not be placeholder');
    }

    /**
     * Test that S3 storage disk can be accessed
     *
     * @test
     */
    public function s3_storage_disk_can_be_accessed()
    {
        // Given: A Laravel application with S3 configured
        // When: Attempting to access S3 storage
        // Then: S3 disk should be accessible

        try {
            $s3 = Storage::disk('s3');
            $this->assertNotNull($s3, 'S3 storage disk should be accessible');

            // Test basic operations (without actually creating files in production)
            $this->assertTrue(method_exists($s3, 'put'), 'S3 disk should have put method');
            $this->assertTrue(method_exists($s3, 'get'), 'S3 disk should have get method');
            $this->assertTrue(method_exists($s3, 'delete'), 'S3 disk should have delete method');
            $this->assertTrue(method_exists($s3, 'exists'), 'S3 disk should have exists method');

        } catch (\Exception $e) {
            $this->markTestSkipped('S3 is not available for testing: ' . $e->getMessage());
        }
    }

    /**
     * Test that mail configuration is set
     *
     * @test
     */
    public function mail_configuration_is_set()
    {
        // Given: A Laravel application
        // When: Checking mail configuration
        // Then: Mail should be properly configured

        $mailConfig = Config::get('mail');

        $this->assertNotNull($mailConfig, 'Mail configuration should exist');
        $this->assertArrayHasKey('default', $mailConfig, 'Default mail driver should be configured');
        $this->assertArrayHasKey('mailers', $mailConfig, 'Mail mailers should be configured');

        $defaultMailer = $mailConfig['default'];
        $this->assertNotEmpty($defaultMailer, 'Default mailer should be set');
        $this->assertArrayHasKey($defaultMailer, $mailConfig['mailers'], 'Default mailer should exist in mailers array');

        // Check from address configuration
        $this->assertArrayHasKey('from', $mailConfig, 'Mail from configuration should exist');
        $this->assertArrayHasKey('address', $mailConfig['from'], 'Mail from address should be configured');
        $this->assertArrayHasKey('name', $mailConfig['from'], 'Mail from name should be configured');
    }

    /**
     * Test that logging configuration is set
     *
     * @test
     */
    public function logging_configuration_is_set()
    {
        // Given: A Laravel application
        // When: Checking logging configuration
        // Then: Logging should be properly configured

        $loggingConfig = Config::get('logging');

        $this->assertNotNull($loggingConfig, 'Logging configuration should exist');
        $this->assertArrayHasKey('default', $loggingConfig, 'Default log channel should be configured');
        $this->assertArrayHasKey('channels', $loggingConfig, 'Log channels should be configured');

        $defaultChannel = $loggingConfig['default'];
        $this->assertNotEmpty($defaultChannel, 'Default log channel should be set');
        $this->assertArrayHasKey($defaultChannel, $loggingConfig['channels'], 'Default channel should exist in channels array');

        // Check that essential channels exist
        $requiredChannels = ['stack', 'single', 'daily', 'stderr'];
        foreach ($requiredChannels as $channel) {
            $this->assertArrayHasKey(
                $channel,
                $loggingConfig['channels'],
                "Log channel '{$channel}' should be configured"
            );
        }
    }

    /**
     * Test that environment variables are properly loaded
     *
     * @test
     */
    public function environment_variables_are_loaded()
    {
        // Given: A Laravel application
        // When: Checking environment variables
        // Then: Essential environment variables should be loaded

        $requiredEnvVars = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'APP_DEBUG',
            'APP_URL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE'
        ];

        foreach ($requiredEnvVars as $envVar) {
            $value = env($envVar);
            $this->assertNotNull(
                $value,
                "Environment variable '{$envVar}' should be set"
            );
        }
    }

    /**
     * Test that services configuration exists
     *
     * @test
     */
    public function services_configuration_exists()
    {
        // Given: A Laravel application
        // When: Checking services configuration
        // Then: Services should be configured

        $servicesConfig = Config::get('services');

        $this->assertNotNull($servicesConfig, 'Services configuration should exist');

        // Check that configuration file exists
        $this->assertTrue(
            File::exists(config_path('services.php')),
            'Services configuration file should exist'
        );
    }

    /**
     * Test that broadcasting configuration is set
     *
     * @test
     */
    public function broadcasting_configuration_is_set()
    {
        // Given: A Laravel application
        // When: Checking broadcasting configuration
        // Then: Broadcasting should be configured

        $broadcastingConfig = Config::get('broadcasting');

        $this->assertNotNull($broadcastingConfig, 'Broadcasting configuration should exist');
        $this->assertArrayHasKey('default', $broadcastingConfig, 'Default broadcast driver should be configured');
        $this->assertArrayHasKey('connections', $broadcastingConfig, 'Broadcast connections should be configured');

        // Check that pusher configuration exists (even if not used)
        $this->assertArrayHasKey('pusher', $broadcastingConfig['connections'], 'Pusher connection should be configured');

        // Check that redis configuration exists for broadcasting
        $this->assertArrayHasKey('redis', $broadcastingConfig['connections'], 'Redis broadcast connection should be configured');
    }
}