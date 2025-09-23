<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Improve workstream ownership cascade behavior
        // Change RESTRICT to SET NULL to prevent blocking user deletion
        // When a user is deleted, workstreams will need manual reassignment

        // Drop existing foreign key constraints
        DB::statement('ALTER TABLE workstreams DROP CONSTRAINT IF EXISTS workstreams_owner_id_foreign');
        DB::statement('ALTER TABLE workstream_permissions DROP CONSTRAINT IF EXISTS workstream_permissions_granted_by_foreign');

        // Recreate with SET NULL behavior
        Schema::table('workstreams', function (Blueprint $table) {
            $table->foreign('owner_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('SET NULL');
        });

        Schema::table('workstream_permissions', function (Blueprint $table) {
            $table->foreign('granted_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('SET NULL');
        });

        // Add trigger to prevent orphaned workstreams
        DB::statement('
            CREATE OR REPLACE FUNCTION prevent_orphaned_workstreams()
            RETURNS TRIGGER AS $$
            BEGIN
                -- If owner_id becomes NULL, log this for manual review
                IF NEW.owner_id IS NULL AND OLD.owner_id IS NOT NULL THEN
                    INSERT INTO ai_jobs (type, status, payload, created_at, updated_at)
                    VALUES (
                        \'workstream_ownership_review\',
                        \'pending\',
                        json_build_object(
                            \'workstream_id\', NEW.id,
                            \'workstream_name\', NEW.name,
                            \'previous_owner_id\', OLD.owner_id,
                            \'requires_manual_assignment\', true
                        ),
                        NOW(),
                        NOW()
                    );
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER workstream_ownership_change_trigger
            AFTER UPDATE ON workstreams
            FOR EACH ROW
            WHEN (OLD.owner_id IS DISTINCT FROM NEW.owner_id)
            EXECUTE FUNCTION prevent_orphaned_workstreams();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS workstream_ownership_change_trigger ON workstreams');
        DB::statement('DROP FUNCTION IF EXISTS prevent_orphaned_workstreams()');

        // Drop foreign key constraints
        DB::statement('ALTER TABLE workstreams DROP CONSTRAINT IF EXISTS workstreams_owner_id_foreign');
        DB::statement('ALTER TABLE workstream_permissions DROP CONSTRAINT IF EXISTS workstream_permissions_granted_by_foreign');

        // Recreate with original RESTRICT behavior
        Schema::table('workstreams', function (Blueprint $table) {
            $table->foreign('owner_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('RESTRICT');
        });

        Schema::table('workstream_permissions', function (Blueprint $table) {
            $table->foreign('granted_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('RESTRICT');
        });
    }
};
