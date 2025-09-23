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
        // Update the enum to include the new relevance types
        DB::statement("ALTER TABLE content_releases DROP CONSTRAINT content_releases_relevance_type_check");
        DB::statement("ALTER TABLE content_releases ADD CONSTRAINT content_releases_relevance_type_check CHECK (relevance_type IN ('primary', 'secondary', 'mentioned', 'related', 'impact'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE content_releases DROP CONSTRAINT content_releases_relevance_type_check");
        DB::statement("ALTER TABLE content_releases ADD CONSTRAINT content_releases_relevance_type_check CHECK (relevance_type IN ('primary', 'secondary', 'mentioned', 'related'))");
    }
};
