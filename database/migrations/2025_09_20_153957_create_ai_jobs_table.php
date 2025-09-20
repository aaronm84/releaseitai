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
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // openai, anthropic
            $table->string('method'); // complete, summarize, etc.
            $table->string('prompt_hash'); // SHA256 hash of prompt for deduplication
            $table->integer('prompt_length');
            $table->json('options')->nullable(); // API options
            $table->enum('status', ['processing', 'completed', 'failed']);
            $table->integer('tokens_used')->nullable();
            $table->decimal('cost', 10, 6)->nullable(); // Cost in USD
            $table->integer('response_length')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            // Indexes for performance
            $table->index(['provider', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['prompt_hash']);
            $table->index(['created_at', 'cost']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};
