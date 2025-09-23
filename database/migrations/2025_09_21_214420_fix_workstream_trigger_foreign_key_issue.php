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
        // Fix the workstream ownership trigger to not violate foreign key constraints
        // Since the user is being deleted, we shouldn't reference them in the ai_jobs record
        DB::statement('
            CREATE OR REPLACE FUNCTION prevent_orphaned_workstreams()
            RETURNS TRIGGER AS $$
            BEGIN
                -- If owner_id becomes NULL, log this for manual review
                IF NEW.owner_id IS NULL AND OLD.owner_id IS NOT NULL THEN
                    INSERT INTO ai_jobs (provider, method, prompt_hash, prompt_length, status, user_id, created_at, updated_at)
                    VALUES (
                        \'system\',
                        \'workstream_ownership_review\',
                        encode(sha256((\'workstream_ownership_review_\' || NEW.id::text)::bytea), \'hex\'),
                        50,
                        \'processing\',
                        NULL, -- Don\'t reference the deleted user
                        NOW(),
                        NOW()
                    );
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the previous broken function
        DB::statement('
            CREATE OR REPLACE FUNCTION prevent_orphaned_workstreams()
            RETURNS TRIGGER AS $$
            BEGIN
                -- If owner_id becomes NULL, log this for manual review
                IF NEW.owner_id IS NULL AND OLD.owner_id IS NOT NULL THEN
                    INSERT INTO ai_jobs (provider, method, prompt_hash, prompt_length, status, user_id, created_at, updated_at)
                    VALUES (
                        \'system\',
                        \'workstream_ownership_review\',
                        encode(sha256((\'workstream_ownership_review_\' || NEW.id::text)::bytea), \'hex\'),
                        50,
                        \'processing\',
                        OLD.owner_id,
                        NOW(),
                        NOW()
                    );
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }
};
