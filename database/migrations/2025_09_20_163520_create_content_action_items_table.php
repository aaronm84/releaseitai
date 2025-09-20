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
        Schema::create('content_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->onDelete('cascade');
            $table->text('action_text');
            $table->foreignId('assignee_stakeholder_id')->nullable()->constrained('stakeholders')->onDelete('set null');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->decimal('confidence_score', 3, 2)->default(0.0);
            $table->text('context')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['content_id', 'status']);
            $table->index(['assignee_stakeholder_id', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('confidence_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_action_items');
    }
};
