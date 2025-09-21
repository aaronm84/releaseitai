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
        // Enable pgvector extension if using PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        // INPUTS table - stores raw content from brain dump, email, docs, etc.
        Schema::create('inputs', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->string('type'); // brain_dump, email, doc, slack, etc.
            $table->string('source'); // manual_entry, email_forward, file_upload, api
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['type']);
            $table->index(['source']);
            $table->index(['created_at']);
        });

        // OUTPUTS table - stores AI-generated results
        Schema::create('outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('input_id')->constrained('inputs')->onDelete('cascade');
            $table->text('content');
            $table->string('type'); // checklist, summary, collateral, release_notes
            $table->string('ai_model'); // gpt-4, claude-3-5-sonnet, etc.
            $table->decimal('quality_score', 8, 6)->nullable(); // 0.000000 to 99.999999
            $table->integer('version')->default(1);
            $table->foreignId('parent_output_id')->nullable()->constrained('outputs')->onDelete('set null');
            $table->boolean('feedback_integrated')->default(false);
            $table->integer('feedback_count')->default(0);
            $table->string('content_format')->default('json'); // json, markdown, text
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['input_id']);
            $table->index(['type']);
            $table->index(['ai_model']);
            $table->index(['quality_score']);
            $table->index(['parent_output_id']);
            $table->index(['created_at']);
        });

        // FEEDBACK table - stores user corrections and signals
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_id')->constrained('outputs')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // inline, behavioral
            $table->string('action'); // accept, edit, reject, task_completed, task_deleted, time_spent
            $table->string('signal_type'); // explicit, passive
            $table->decimal('confidence', 8, 6); // 0.000000 to 1.000000
            $table->json('metadata')->nullable(); // corrections, reasons, timing data
            $table->timestamps();

            // Foreign key indexes
            $table->index(['output_id']);
            $table->index(['user_id']);

            // Query optimization indexes
            $table->index(['type']);
            $table->index(['action']);
            $table->index(['signal_type']);
            $table->index(['confidence']);
            $table->index(['created_at']);

            // Composite indexes for common queries
            $table->index(['output_id', 'action']);
            $table->index(['user_id', 'type']);

            // Note: Check constraints will be added after table creation
        });

        // EMBEDDINGS table - stores vectors for similarity search
        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->string('content_type'); // App\Models\Input, App\Models\Output

            // Store vectors as text for now, will upgrade to pgvector later
            $table->text('vector');

            $table->string('model'); // text-embedding-ada-002, etc.
            $table->integer('dimensions'); // 1536 for ada-002
            $table->boolean('normalized')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Polymorphic relationship indexes
            $table->index(['content_id', 'content_type']);

            // Query optimization indexes
            $table->index(['model']);
            $table->index(['dimensions']);
            $table->index(['created_at']);

            // Unique constraint for content (one embedding per content item per model)
            $table->unique(['content_id', 'content_type', 'model'], 'unique_content_model_embedding');

        });

        // Add constraints after table creation
        if (DB::getDriverName() === 'pgsql') {
            // Add check constraint for confidence range
            DB::statement('ALTER TABLE feedback ADD CONSTRAINT confidence_range CHECK (confidence >= 0 AND confidence <= 1)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embeddings');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('outputs');
        Schema::dropIfExists('inputs');

        // Note: We don't drop the pgvector extension as it might be used elsewhere
    }
};