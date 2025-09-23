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
        // Fix the embedding validation trigger function with correct escaping
        DB::statement("
            CREATE OR REPLACE FUNCTION validate_embedding_content()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NEW.content_type = 'App\\Models\\Input' THEN
                    IF NOT EXISTS (SELECT 1 FROM inputs WHERE id = NEW.content_id) THEN
                        RAISE EXCEPTION 'Invalid content_id % for content_type %', NEW.content_id, NEW.content_type;
                    END IF;
                ELSIF NEW.content_type = 'App\\Models\\Output' THEN
                    IF NOT EXISTS (SELECT 1 FROM outputs WHERE id = NEW.content_id) THEN
                        RAISE EXCEPTION 'Invalid content_id % for content_type %', NEW.content_id, NEW.content_type;
                    END IF;
                ELSIF NEW.content_type = 'App\\Models\\Feedback' THEN
                    IF NOT EXISTS (SELECT 1 FROM feedback WHERE id = NEW.content_id) THEN
                        RAISE EXCEPTION 'Invalid content_id % for content_type %', NEW.content_id, NEW.content_type;
                    END IF;
                ELSE
                    RAISE EXCEPTION 'Invalid content_type %', NEW.content_type;
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original broken function
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
    }
};
