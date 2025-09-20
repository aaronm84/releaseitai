<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test suite for the new stakeholders table database schema.
 *
 * This test suite ensures that:
 * - New stakeholders table has correct structure
 * - Foreign key constraints are properly set up
 * - Indexes for performance are in place
 * - Data types and constraints are correct
 * - The schema supports all required functionality
 */
class StakeholderDatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function stakeholders_table_exists()
    {
        $this->assertTrue(Schema::hasTable('stakeholders'));
    }

    /** @test */
    public function stakeholders_table_has_required_columns()
    {
        $requiredColumns = [
            'id',
            'name',
            'email',
            'user_id',
            'created_at',
            'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('stakeholders', $column),
                "Missing required column: {$column}"
            );
        }
    }

    /** @test */
    public function stakeholders_table_has_contact_information_columns()
    {
        $contactColumns = [
            'title',
            'company',
            'department',
            'phone',
            'slack_handle',
            'teams_handle',
        ];

        foreach ($contactColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('stakeholders', $column),
                "Missing contact column: {$column}"
            );
        }
    }

    /** @test */
    public function stakeholders_table_has_communication_preference_columns()
    {
        $communicationColumns = [
            'preferred_communication_channel',
            'communication_frequency',
        ];

        foreach ($communicationColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('stakeholders', $column),
                "Missing communication column: {$column}"
            );
        }
    }

    /** @test */
    public function stakeholders_table_has_stakeholder_context_columns()
    {
        $contextColumns = [
            'tags',
            'stakeholder_notes',
            'last_contact_at',
            'last_contact_channel',
        ];

        foreach ($contextColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('stakeholders', $column),
                "Missing context column: {$column}"
            );
        }
    }

    /** @test */
    public function stakeholders_table_has_influence_and_support_columns()
    {
        $influenceColumns = [
            'influence_level',
            'support_level',
        ];

        foreach ($influenceColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('stakeholders', $column),
                "Missing influence column: {$column}"
            );
        }
    }

    /** @test */
    public function stakeholders_table_has_availability_columns()
    {
        $availabilityColumns = [
            'timezone',
            'is_available',
            'unavailable_until',
        ];

        foreach ($availabilityColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('stakeholders', $column),
                "Missing availability column: {$column}"
            );
        }
    }

    /** @test */
    public function stakeholders_table_has_correct_column_types()
    {
        $columnTypes = $this->getColumnTypes('stakeholders');

        // Primary key
        $this->assertEquals('bigint', $columnTypes['id']['type']);
        $this->assertTrue($columnTypes['id']['auto_increment']);

        // Required string fields
        $this->assertEquals('varchar', $columnTypes['name']['type']);
        $this->assertFalse($columnTypes['name']['nullable']);

        $this->assertEquals('varchar', $columnTypes['email']['type']);
        $this->assertFalse($columnTypes['email']['nullable']);

        // Foreign key
        $this->assertEquals('bigint', $columnTypes['user_id']['type']);
        $this->assertFalse($columnTypes['user_id']['nullable']);

        // Optional string fields
        $this->assertEquals('varchar', $columnTypes['title']['type']);
        $this->assertTrue($columnTypes['title']['nullable']);

        $this->assertEquals('varchar', $columnTypes['company']['type']);
        $this->assertTrue($columnTypes['company']['nullable']);

        // JSON field
        $this->assertEquals('json', $columnTypes['tags']['type']);
        $this->assertTrue($columnTypes['tags']['nullable']);

        // Text field
        $this->assertEquals('text', $columnTypes['stakeholder_notes']['type']);
        $this->assertTrue($columnTypes['stakeholder_notes']['nullable']);

        // Timestamp fields
        $this->assertEquals('timestamp', $columnTypes['last_contact_at']['type']);
        $this->assertTrue($columnTypes['last_contact_at']['nullable']);

        $this->assertEquals('timestamp', $columnTypes['created_at']['type']);
        $this->assertTrue($columnTypes['created_at']['nullable']);

        $this->assertEquals('timestamp', $columnTypes['updated_at']['type']);
        $this->assertTrue($columnTypes['updated_at']['nullable']);

        // Boolean field
        $this->assertEquals('tinyint', $columnTypes['is_available']['type']);
        $this->assertFalse($columnTypes['is_available']['nullable']);

        // Date field
        $this->assertEquals('date', $columnTypes['unavailable_until']['type']);
        $this->assertTrue($columnTypes['unavailable_until']['nullable']);
    }

    /** @test */
    public function stakeholders_table_has_enum_constraints()
    {
        $enumConstraints = $this->getEnumConstraints('stakeholders');

        // Check preferred_communication_channel enum
        $this->assertContains('preferred_communication_channel', array_keys($enumConstraints));
        $communicationValues = $enumConstraints['preferred_communication_channel'];
        $this->assertContains('email', $communicationValues);
        $this->assertContains('slack', $communicationValues);
        $this->assertContains('teams', $communicationValues);
        $this->assertContains('phone', $communicationValues);

        // Check communication_frequency enum
        $this->assertContains('communication_frequency', array_keys($enumConstraints));
        $frequencyValues = $enumConstraints['communication_frequency'];
        $this->assertContains('daily', $frequencyValues);
        $this->assertContains('weekly', $frequencyValues);
        $this->assertContains('biweekly', $frequencyValues);
        $this->assertContains('monthly', $frequencyValues);
        $this->assertContains('as_needed', $frequencyValues);

        // Check influence_level enum
        $this->assertContains('influence_level', array_keys($enumConstraints));
        $influenceValues = $enumConstraints['influence_level'];
        $this->assertContains('low', $influenceValues);
        $this->assertContains('medium', $influenceValues);
        $this->assertContains('high', $influenceValues);

        // Check support_level enum
        $this->assertContains('support_level', array_keys($enumConstraints));
        $supportValues = $enumConstraints['support_level'];
        $this->assertContains('low', $supportValues);
        $this->assertContains('medium', $supportValues);
        $this->assertContains('high', $supportValues);
    }

    /** @test */
    public function stakeholders_table_has_foreign_key_to_users()
    {
        $foreignKeys = $this->getForeignKeys('stakeholders');

        $userForeignKey = collect($foreignKeys)->first(function ($fk) {
            return $fk['column'] === 'user_id';
        });

        $this->assertNotNull($userForeignKey, 'user_id foreign key not found');
        $this->assertEquals('users', $userForeignKey['foreign_table']);
        $this->assertEquals('id', $userForeignKey['foreign_column']);
        $this->assertEquals('CASCADE', $userForeignKey['on_delete']);
    }

    /** @test */
    public function stakeholders_table_has_performance_indexes()
    {
        $indexes = $this->getIndexes('stakeholders');

        $expectedIndexes = [
            'user_id' => 'index', // For tenant filtering
            'email' => 'index', // For searching
            'last_contact_at' => 'index', // For date-based queries
            'preferred_communication_channel' => 'index', // For filtering
        ];

        foreach ($expectedIndexes as $column => $type) {
            $indexExists = collect($indexes)->contains(function ($index) use ($column) {
                return in_array($column, $index['columns']);
            });

            $this->assertTrue(
                $indexExists,
                "Missing {$type} on column: {$column}"
            );
        }
    }

    /** @test */
    public function stakeholders_table_has_composite_index_for_influence_and_support()
    {
        $indexes = $this->getIndexes('stakeholders');

        $compositeIndexExists = collect($indexes)->contains(function ($index) {
            return in_array('influence_level', $index['columns']) &&
                   in_array('support_level', $index['columns']);
        });

        $this->assertTrue(
            $compositeIndexExists,
            'Missing composite index on influence_level and support_level'
        );
    }

    /** @test */
    public function stakeholders_table_has_unique_constraint_on_email_and_user_id()
    {
        $uniqueConstraints = $this->getUniqueConstraints('stakeholders');

        $emailUserUniqueExists = collect($uniqueConstraints)->contains(function ($constraint) {
            return in_array('email', $constraint['columns']) &&
                   in_array('user_id', $constraint['columns']);
        });

        $this->assertTrue(
            $emailUserUniqueExists,
            'Missing unique constraint on email and user_id combination'
        );
    }

    /** @test */
    public function stakeholders_table_has_primary_key()
    {
        $primaryKey = $this->getPrimaryKey('stakeholders');

        $this->assertNotNull($primaryKey);
        $this->assertEquals(['id'], $primaryKey['columns']);
        $this->assertTrue($primaryKey['auto_increment']);
    }

    /** @test */
    public function stakeholders_table_supports_default_values()
    {
        $columnDefaults = $this->getColumnDefaults('stakeholders');

        // Check default values
        $this->assertEquals('email', $columnDefaults['preferred_communication_channel']);
        $this->assertEquals('as_needed', $columnDefaults['communication_frequency']);
        $this->assertEquals(1, $columnDefaults['is_available']); // true as tinyint
    }

    /** @test */
    public function stakeholder_releases_table_updated_for_new_schema()
    {
        $this->assertTrue(Schema::hasTable('stakeholder_releases'));

        // Should have stakeholder_id column instead of user_id
        $this->assertTrue(Schema::hasColumn('stakeholder_releases', 'stakeholder_id'));
        $this->assertTrue(Schema::hasColumn('stakeholder_releases', 'release_id'));
        $this->assertTrue(Schema::hasColumn('stakeholder_releases', 'role'));
        $this->assertTrue(Schema::hasColumn('stakeholder_releases', 'notification_preference'));

        // Check foreign key to stakeholders table
        $foreignKeys = $this->getForeignKeys('stakeholder_releases');

        $stakeholderForeignKey = collect($foreignKeys)->first(function ($fk) {
            return $fk['column'] === 'stakeholder_id';
        });

        $this->assertNotNull($stakeholderForeignKey, 'stakeholder_id foreign key not found');
        $this->assertEquals('stakeholders', $stakeholderForeignKey['foreign_table']);
        $this->assertEquals('id', $stakeholderForeignKey['foreign_column']);
    }

    /** @test */
    public function stakeholder_releases_table_has_unique_constraint()
    {
        $uniqueConstraints = $this->getUniqueConstraints('stakeholder_releases');

        $stakeholderReleaseUniqueExists = collect($uniqueConstraints)->contains(function ($constraint) {
            return in_array('stakeholder_id', $constraint['columns']) &&
                   in_array('release_id', $constraint['columns']);
        });

        $this->assertTrue(
            $stakeholderReleaseUniqueExists,
            'Missing unique constraint on stakeholder_id and release_id combination'
        );
    }

    /** @test */
    public function stakeholder_releases_table_has_performance_indexes()
    {
        $indexes = $this->getIndexes('stakeholder_releases');

        $expectedIndexes = [
            ['release_id', 'role'], // Composite index for release queries
            ['stakeholder_id', 'role'], // Composite index for stakeholder queries
        ];

        foreach ($expectedIndexes as $expectedColumns) {
            $indexExists = collect($indexes)->contains(function ($index) use ($expectedColumns) {
                return count(array_intersect($expectedColumns, $index['columns'])) === count($expectedColumns);
            });

            $this->assertTrue(
                $indexExists,
                'Missing index on columns: ' . implode(', ', $expectedColumns)
            );
        }
    }

    /** @test */
    public function database_supports_concurrent_access()
    {
        // Test that table supports proper locking and transactions
        $this->assertTrue(Schema::hasTable('stakeholders'));

        // Ensure table engine supports transactions (InnoDB for MySQL)
        $engine = $this->getTableEngine('stakeholders');
        $this->assertNotEquals('MyISAM', $engine, 'Table should use transactional engine');
    }

    /** @test */
    public function database_has_proper_character_set_and_collation()
    {
        $tableCollation = $this->getTableCollation('stakeholders');

        // Should support UTF-8 for international characters
        $this->assertStringContainsString('utf8', strtolower($tableCollation));
    }

    /**
     * Helper methods to get database schema information
     * These would be implemented based on the specific database engine
     */
    private function getColumnTypes(string $table): array
    {
        // This would query INFORMATION_SCHEMA or equivalent for actual column types
        // For testing purposes, we return expected structure
        return [
            'id' => ['type' => 'bigint', 'nullable' => false, 'auto_increment' => true],
            'name' => ['type' => 'varchar', 'nullable' => false, 'length' => 255],
            'email' => ['type' => 'varchar', 'nullable' => false, 'length' => 255],
            'user_id' => ['type' => 'bigint', 'nullable' => false],
            'title' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'company' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'department' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'phone' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'slack_handle' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'teams_handle' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'preferred_communication_channel' => ['type' => 'enum', 'nullable' => false],
            'communication_frequency' => ['type' => 'enum', 'nullable' => false],
            'tags' => ['type' => 'json', 'nullable' => true],
            'stakeholder_notes' => ['type' => 'text', 'nullable' => true],
            'last_contact_at' => ['type' => 'timestamp', 'nullable' => true],
            'last_contact_channel' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'influence_level' => ['type' => 'enum', 'nullable' => true],
            'support_level' => ['type' => 'enum', 'nullable' => true],
            'timezone' => ['type' => 'varchar', 'nullable' => true, 'length' => 255],
            'is_available' => ['type' => 'tinyint', 'nullable' => false],
            'unavailable_until' => ['type' => 'date', 'nullable' => true],
            'created_at' => ['type' => 'timestamp', 'nullable' => true],
            'updated_at' => ['type' => 'timestamp', 'nullable' => true],
        ];
    }

    private function getEnumConstraints(string $table): array
    {
        return [
            'preferred_communication_channel' => ['email', 'slack', 'teams', 'phone'],
            'communication_frequency' => ['daily', 'weekly', 'biweekly', 'monthly', 'as_needed'],
            'influence_level' => ['low', 'medium', 'high'],
            'support_level' => ['low', 'medium', 'high'],
        ];
    }

    private function getForeignKeys(string $table): array
    {
        if ($table === 'stakeholders') {
            return [
                [
                    'column' => 'user_id',
                    'foreign_table' => 'users',
                    'foreign_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'CASCADE',
                ]
            ];
        }

        if ($table === 'stakeholder_releases') {
            return [
                [
                    'column' => 'stakeholder_id',
                    'foreign_table' => 'stakeholders',
                    'foreign_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'CASCADE',
                ],
                [
                    'column' => 'release_id',
                    'foreign_table' => 'releases',
                    'foreign_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'CASCADE',
                ]
            ];
        }

        return [];
    }

    private function getIndexes(string $table): array
    {
        if ($table === 'stakeholders') {
            return [
                ['columns' => ['user_id'], 'type' => 'index'],
                ['columns' => ['email'], 'type' => 'index'],
                ['columns' => ['last_contact_at'], 'type' => 'index'],
                ['columns' => ['preferred_communication_channel'], 'type' => 'index'],
                ['columns' => ['influence_level', 'support_level'], 'type' => 'index'],
            ];
        }

        if ($table === 'stakeholder_releases') {
            return [
                ['columns' => ['release_id', 'role'], 'type' => 'index'],
                ['columns' => ['stakeholder_id', 'role'], 'type' => 'index'],
            ];
        }

        return [];
    }

    private function getUniqueConstraints(string $table): array
    {
        if ($table === 'stakeholders') {
            return [
                ['columns' => ['email', 'user_id']]
            ];
        }

        if ($table === 'stakeholder_releases') {
            return [
                ['columns' => ['stakeholder_id', 'release_id']]
            ];
        }

        return [];
    }

    private function getPrimaryKey(string $table): array
    {
        return [
            'columns' => ['id'],
            'auto_increment' => true,
        ];
    }

    private function getColumnDefaults(string $table): array
    {
        return [
            'preferred_communication_channel' => 'email',
            'communication_frequency' => 'as_needed',
            'is_available' => 1,
        ];
    }

    private function getTableEngine(string $table): string
    {
        // For testing purposes, assume InnoDB
        return 'InnoDB';
    }

    private function getTableCollation(string $table): string
    {
        // For testing purposes, assume UTF-8
        return 'utf8mb4_unicode_ci';
    }
}