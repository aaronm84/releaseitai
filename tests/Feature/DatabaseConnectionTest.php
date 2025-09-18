<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class DatabaseConnectionTest extends TestCase
{
    /**
     * Test that database connection is properly configured
     *
     * @test
     */
    public function database_connection_is_configured()
    {
        // Given: A Laravel application with database configuration
        // When: Checking database configuration
        // Then: Database connection should be properly configured

        $defaultConnection = Config::get('database.default');
        $this->assertNotEmpty($defaultConnection, 'Default database connection should be set');

        $connections = Config::get('database.connections');
        $this->assertArrayHasKey($defaultConnection, $connections, 'Default connection should exist in connections array');

        $connectionConfig = $connections[$defaultConnection];
        $this->assertArrayHasKey('driver', $connectionConfig, 'Database driver should be specified');
        $this->assertArrayHasKey('host', $connectionConfig, 'Database host should be specified');
        $this->assertArrayHasKey('database', $connectionConfig, 'Database name should be specified');
    }

    /**
     * Test that database connection can be established
     *
     * @test
     */
    public function database_connection_can_be_established()
    {
        // Given: A Laravel application with database configuration
        // When: Attempting to connect to the database
        // Then: Connection should be successful

        try {
            DB::connection()->getPdo();
            $this->assertTrue(true, 'Database connection established successfully');
        } catch (\Exception $e) {
            $this->fail('Failed to establish database connection: ' . $e->getMessage());
        }
    }

    /**
     * Test that database can execute basic queries
     *
     * @test
     */
    public function database_can_execute_basic_queries()
    {
        // Given: A connected database
        // When: Executing a basic query
        // Then: Query should execute successfully

        $result = DB::select('SELECT 1 as test');
        $this->assertCount(1, $result, 'Query should return one result');
        $this->assertEquals(1, $result[0]->test, 'Query should return expected value');
    }

    /**
     * Test that migration files directory exists
     *
     * @test
     */
    public function migration_directory_exists()
    {
        // Given: A Laravel application
        // When: Checking for migrations directory
        // Then: Migrations directory should exist

        $migrationPath = database_path('migrations');
        $this->assertTrue(
            File::isDirectory($migrationPath),
            'Database migrations directory should exist'
        );
    }

    /**
     * Test that basic Laravel migration files exist
     *
     * @test
     */
    public function basic_migration_files_exist()
    {
        // Given: A Laravel application
        // When: Checking for essential migration files
        // Then: Basic migration files should exist

        $migrationPath = database_path('migrations');
        $migrationFiles = File::files($migrationPath);

        $requiredMigrations = [
            'create_users_table',
            'create_password_reset_tokens_table',
            'create_sessions_table'
        ];

        foreach ($requiredMigrations as $migration) {
            $found = false;
            foreach ($migrationFiles as $file) {
                if (str_contains($file->getFilename(), $migration)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Migration '{$migration}' should exist");
        }
    }

    /**
     * Test that migrations can be run successfully
     *
     * @test
     */
    public function migrations_can_be_run()
    {
        // Given: A Laravel application with migrations
        // When: Running migrations
        // Then: Migrations should execute without errors

        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $exitCode = Artisan::output();
            $this->assertTrue(true, 'Migrations should run without errors');
        } catch (\Exception $e) {
            $this->fail('Migrations failed to run: ' . $e->getMessage());
        }
    }

    /**
     * Test that users table is created with correct structure
     *
     * @test
     */
    public function users_table_has_correct_structure()
    {
        // Given: A Laravel application with migrations run
        // When: Checking users table structure
        // Then: Users table should have required columns

        // First ensure migrations are run
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('users'), 'Users table should exist');

        $requiredColumns = [
            'id',
            'name',
            'email',
            'email_verified_at',
            'password',
            'remember_token',
            'created_at',
            'updated_at'
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "Users table should have '{$column}' column"
            );
        }

        // Check for unique email constraint
        $indexes = Schema::getIndexes('users');
        $emailIsUnique = false;
        foreach ($indexes as $index) {
            if (in_array('email', $index['columns']) && $index['unique']) {
                $emailIsUnique = true;
                break;
            }
        }
        $this->assertTrue($emailIsUnique, 'Email column should have unique constraint');
    }

    /**
     * Test that password reset tokens table exists
     *
     * @test
     */
    public function password_reset_tokens_table_exists()
    {
        // Given: A Laravel application with migrations run
        // When: Checking password reset tokens table
        // Then: Table should exist with correct structure

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('password_reset_tokens'), 'Password reset tokens table should exist');

        $requiredColumns = ['email', 'token', 'created_at'];
        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('password_reset_tokens', $column),
                "Password reset tokens table should have '{$column}' column"
            );
        }
    }

    /**
     * Test that sessions table exists for session-based authentication
     *
     * @test
     */
    public function sessions_table_exists()
    {
        // Given: A Laravel application with migrations run
        // When: Checking sessions table
        // Then: Table should exist with correct structure

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('sessions'), 'Sessions table should exist');

        $requiredColumns = ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'];
        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('sessions', $column),
                "Sessions table should have '{$column}' column"
            );
        }
    }

    /**
     * Test that database seeders directory exists
     *
     * @test
     */
    public function seeders_directory_exists()
    {
        // Given: A Laravel application
        // When: Checking for seeders directory
        // Then: Seeders directory should exist

        $seedersPath = database_path('seeders');
        $this->assertTrue(
            File::isDirectory($seedersPath),
            'Database seeders directory should exist'
        );

        $this->assertTrue(
            File::exists($seedersPath . '/DatabaseSeeder.php'),
            'DatabaseSeeder.php should exist'
        );
    }

    /**
     * Test that database can be rolled back
     *
     * @test
     */
    public function migrations_can_be_rolled_back()
    {
        // Given: A Laravel application with migrations run
        // When: Rolling back migrations
        // Then: Rollback should execute without errors

        // First run migrations
        Artisan::call('migrate:fresh', ['--force' => true]);

        try {
            Artisan::call('migrate:rollback', ['--force' => true]);
            $this->assertTrue(true, 'Migration rollback should execute without errors');
        } catch (\Exception $e) {
            $this->fail('Migration rollback failed: ' . $e->getMessage());
        }
    }

    /**
     * Test that database factory directory exists
     *
     * @test
     */
    public function factories_directory_exists()
    {
        // Given: A Laravel application
        // When: Checking for factories directory
        // Then: Factories directory should exist

        $factoriesPath = database_path('factories');
        $this->assertTrue(
            File::isDirectory($factoriesPath),
            'Database factories directory should exist'
        );

        $this->assertTrue(
            File::exists($factoriesPath . '/UserFactory.php'),
            'UserFactory.php should exist'
        );
    }
}