<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table for storing cache operation metrics
        Schema::create('cache_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 50)->index(); // cache_read, cache_write, etc.
            $table->decimal('duration_ms', 10, 2); // Operation duration in milliseconds
            $table->boolean('success')->default(true)->index(); // Whether operation succeeded
            $table->json('metadata')->nullable(); // Additional operation metadata
            $table->timestamps();

            // Indexes for performance
            $table->index(['operation', 'created_at']);
            $table->index(['success', 'created_at']);
            $table->index('created_at'); // For time-based queries
        });

        // Table for storing cache health reports
        Schema::create('cache_health_reports', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp')->index();
            $table->enum('overall_status', ['healthy', 'warning', 'degraded', 'critical'])->index();
            $table->decimal('hit_ratio', 5, 2)->default(0); // Percentage
            $table->decimal('avg_read_time', 8, 2)->default(0); // Milliseconds
            $table->decimal('avg_write_time', 8, 2)->default(0); // Milliseconds
            $table->integer('error_count')->default(0);
            $table->decimal('redis_memory_usage', 5, 2)->default(0); // Percentage
            $table->json('data'); // Full health report data
            $table->timestamps();

            // Indexes for monitoring queries
            $table->index(['overall_status', 'timestamp']);
            $table->index(['timestamp', 'hit_ratio']);
        });

        // Table for storing cache invalidation events
        Schema::create('cache_invalidation_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index(); // workstream_hierarchy, feedback, permissions, etc.
            $table->unsignedBigInteger('entity_id')->nullable()->index(); // ID of the entity that triggered invalidation
            $table->string('entity_type', 100)->nullable(); // Class name of the entity
            $table->json('tags')->nullable(); // Cache tags that were invalidated
            $table->integer('keys_invalidated')->default(0); // Number of cache keys affected
            $table->json('changes')->nullable(); // What changed to trigger invalidation
            $table->decimal('duration_ms', 10, 2)->default(0); // How long invalidation took
            $table->timestamps();

            // Indexes for analysis
            $table->index(['event_type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at'); // For time-based analysis
        });

        // Table for storing cache warming events
        Schema::create('cache_warming_events', function (Blueprint $table) {
            $table->id();
            $table->string('strategy', 50)->index(); // eager, lazy, manual
            $table->integer('keys_warmed')->default(0);
            $table->integer('keys_failed')->default(0);
            $table->decimal('duration_ms', 10, 2)->default(0);
            $table->json('warming_specs')->nullable(); // Details of what was warmed
            $table->json('results')->nullable(); // Results of warming operations
            $table->timestamps();

            // Indexes for monitoring
            $table->index(['strategy', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache_warming_events');
        Schema::dropIfExists('cache_invalidation_events');
        Schema::dropIfExists('cache_health_reports');
        Schema::dropIfExists('cache_metrics');
    }
};