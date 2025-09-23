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
        // Check if index already exists before creating it
        $indexExists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'embeddings_content_id_content_type_index'");

        if (empty($indexExists)) {
            Schema::table('embeddings', function (Blueprint $table) {
                // Add index for better performance on content lookups
                $table->index(['content_id', 'content_type']);
            });
        }

        // Add trigger to ensure content_id references valid records
        // This is safer than foreign keys for polymorphic relationships
        DB::statement('
            CREATE OR REPLACE FUNCTION validate_embedding_content()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.content_type = \'App\\\\Models\\\\Input\' THEN
                    IF NOT EXISTS (SELECT 1 FROM inputs WHERE id = NEW.content_id) THEN
                        RAISE EXCEPTION \'Invalid content_id % for content_type %\', NEW.content_id, NEW.content_type;
                    END IF;
                ELSIF NEW.content_type = \'App\\\\Models\\\\Output\' THEN
                    IF NOT EXISTS (SELECT 1 FROM outputs WHERE id = NEW.content_id) THEN
                        RAISE EXCEPTION \'Invalid content_id % for content_type %\', NEW.content_id, NEW.content_type;
                    END IF;
                ELSIF NEW.content_type = \'App\\\\Models\\\\Feedback\' THEN
                    IF NOT EXISTS (SELECT 1 FROM feedback WHERE id = NEW.content_id) THEN
                        RAISE EXCEPTION \'Invalid content_id % for content_type %\', NEW.content_id, NEW.content_type;
                    END IF;
                ELSE
                    RAISE EXCEPTION \'Invalid content_type %\', NEW.content_type;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER embedding_content_validation_trigger
            BEFORE INSERT OR UPDATE ON embeddings
            FOR EACH ROW
            EXECUTE FUNCTION validate_embedding_content();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS embedding_content_validation_trigger ON embeddings');
        DB::statement('DROP FUNCTION IF EXISTS validate_embedding_content()');

        // Only drop the index if it was created by this migration
        $indexExists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'embeddings_content_id_content_type_index'");
        if (!empty($indexExists)) {
            Schema::table('embeddings', function (Blueprint $table) {
                $table->dropIndex(['content_id', 'content_type']);
            });
        }
    }
};
