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
        Schema::create('approval_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('responder_id')->constrained('users')->onDelete('cascade');
            $table->string('decision'); // approved, rejected, needs_changes
            $table->text('comments')->nullable();
            $table->json('conditions')->nullable(); // Array of conditions if approved with conditions
            $table->timestamp('responded_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index('approval_request_id');
            $table->index('responder_id');
            $table->index('decision');

            // Ensure only one response per approval request
            $table->unique('approval_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_responses');
    }
};
