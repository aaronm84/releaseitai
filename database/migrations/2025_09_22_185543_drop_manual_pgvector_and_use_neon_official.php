<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration optimizes pgvector setup for different environments:
     * - Neon: Uses their optimized extension to resolve storage quota issues
     * - DigitalOcean: Uses standard pgvector with production-optimized settings
     */
    public function up(): void
    {
        // Step 1: Drop the space-consuming vector index
        DB::statement('DROP INDEX IF EXISTS embeddings_vector_cosine_idx');

        // Step 2: Drop the manual vector extension (this will cascade and remove vector columns)
        // Note: This will temporarily break vector functionality but save space
        DB::statement('DROP EXTENSION IF EXISTS vector CASCADE');

        // Step 3: Enable pgvector extension (works on both Neon and DigitalOcean)
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Step 4: Recreate the vector column with proper dimensions
        Schema::table('embeddings', function (Blueprint $table) {
            // Add back the vector column with 1536 dimensions (OpenAI standard)
            $table->vector('vector', 1536)->nullable();
        });

        // Step 5: Create environment-optimized indexes
        $this->createOptimizedVectorIndex();
    }

    /**
     * Create vector index optimized for the current environment
     */
    private function createOptimizedVectorIndex(): void
    {
        $host = config('database.connections.pgsql.host');

        if (str_contains($host, 'neon.tech')) {
            // Neon-optimized index (smaller, more efficient)
            DB::statement('CREATE INDEX embeddings_vector_idx ON embeddings USING hnsw (vector vector_cosine_ops) WITH (m = 16, ef_construction = 64)');
        } else {
            // DigitalOcean/standard PostgreSQL optimized index
            // Using slightly more conservative settings for better performance on standard hardware
            DB::statement('CREATE INDEX embeddings_vector_idx ON embeddings USING hnsw (vector vector_cosine_ops) WITH (m = 16, ef_construction = 200)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new efficient index
        DB::statement('DROP INDEX IF EXISTS embeddings_vector_idx');

        // Drop the vector column
        Schema::table('embeddings', function (Blueprint $table) {
            $table->dropColumn('vector');
        });

        // This is a one-way migration - we don't want to go back to the space-consuming setup
        // If rollback is needed, manually reinstall the previous configuration
    }
};
