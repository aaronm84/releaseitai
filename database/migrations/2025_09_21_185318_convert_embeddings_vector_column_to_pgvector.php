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
        // Ensure pgvector extension is enabled
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Convert the vector column from text to vector type with dimensions
        DB::statement('ALTER TABLE embeddings ALTER COLUMN vector TYPE vector(1536) USING vector::vector');

        // Create an index for vector similarity search
        DB::statement('CREATE INDEX IF NOT EXISTS embeddings_vector_cosine_idx ON embeddings USING ivfflat (vector vector_cosine_ops) WITH (lists = 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the vector index
        DB::statement('DROP INDEX IF EXISTS embeddings_vector_cosine_idx');

        // Convert back to text
        DB::statement('ALTER TABLE embeddings ALTER COLUMN vector TYPE text USING vector::text');
    }
};
