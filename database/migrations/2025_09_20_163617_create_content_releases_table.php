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
        Schema::create('content_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->onDelete('cascade');
            $table->foreignId('release_id')->constrained()->onDelete('cascade');
            $table->enum('relevance_type', ['primary', 'secondary', 'mentioned', 'related'])->default('related');
            $table->decimal('confidence_score', 3, 2)->default(0.0);
            $table->text('context')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['content_id', 'release_id']);

            // Indexes for performance
            $table->index(['content_id', 'relevance_type']);
            $table->index(['release_id', 'confidence_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_releases');
    }
};
