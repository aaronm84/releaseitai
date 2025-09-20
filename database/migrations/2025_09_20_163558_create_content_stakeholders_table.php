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
        Schema::create('content_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->onDelete('cascade');
            $table->foreignId('stakeholder_id')->constrained()->onDelete('cascade');
            $table->enum('mention_type', ['direct_mention', 'cc', 'assignee', 'participant'])->default('direct_mention');
            $table->decimal('confidence_score', 3, 2)->default(0.0);
            $table->text('context')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['content_id', 'stakeholder_id']);

            // Indexes for performance
            $table->index(['content_id', 'mention_type']);
            $table->index(['stakeholder_id', 'confidence_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_stakeholders');
    }
};
