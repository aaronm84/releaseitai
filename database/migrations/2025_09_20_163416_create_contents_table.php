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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['email', 'file', 'manual', 'meeting_notes', 'slack', 'teams']);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->longText('raw_content')->nullable();
            $table->json('metadata')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('ai_summary')->nullable();
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'status']);
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
