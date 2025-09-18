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
        Schema::create('checklist_item_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained('checklist_items')->onDelete('cascade');
            $table->foreignId('assignee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('release_id')->constrained('releases')->onDelete('cascade');
            $table->datetime('due_date');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'blocked', 'cancelled'])->default('pending');
            $table->datetime('assigned_at')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->text('notes')->nullable();

            // SLA tracking
            $table->datetime('sla_deadline')->nullable();

            // Escalation tracking
            $table->boolean('escalated')->default(false);
            $table->datetime('escalated_at')->nullable();
            $table->text('escalation_reason')->nullable();

            // Reassignment tracking
            $table->boolean('reassigned')->default(false);
            $table->text('reassignment_reason')->nullable();
            $table->foreignId('previous_assignee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['release_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index(['sla_deadline', 'status']);
            $table->index(['escalated', 'status']);

            // Unique constraint to prevent duplicate assignments
            $table->unique(['checklist_item_id', 'release_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_item_assignments');
    }
};
