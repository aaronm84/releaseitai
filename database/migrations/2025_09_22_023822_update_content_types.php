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
        // Update the enum to include the new content types
        DB::statement("ALTER TABLE contents DROP CONSTRAINT contents_type_check");
        DB::statement("ALTER TABLE contents ADD CONSTRAINT contents_type_check CHECK (type IN ('email', 'file', 'manual', 'meeting_notes', 'slack', 'teams', 'brain_dump', 'private_note', 'financial_data', 'collaborative_document'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE contents DROP CONSTRAINT contents_type_check");
        DB::statement("ALTER TABLE contents ADD CONSTRAINT contents_type_check CHECK (type IN ('email', 'file', 'manual', 'meeting_notes', 'slack', 'teams'))");
    }
};
