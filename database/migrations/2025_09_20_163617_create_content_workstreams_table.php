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
        Schema::create('content_workstreams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->onDelete('cascade');
            $table->foreignId('workstream_id')->constrained()->onDelete('cascade');
            $table->enum('relevance_type', ['primary', 'secondary', 'mentioned', 'related'])->default('related');
            $table->decimal('confidence_score', 3, 2)->default(0.0);
            $table->text('context')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['content_id', 'workstream_id']);

            // Indexes for performance
            $table->index(['content_id', 'relevance_type']);
            $table->index(['workstream_id', 'confidence_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_workstreams');
    }
};
