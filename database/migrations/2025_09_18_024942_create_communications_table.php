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
        Schema::create('communications', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('release_id')->constrained('releases')->onDelete('cascade');
            $table->foreignId('initiated_by_user_id')->constrained('users')->onDelete('cascade');

            // Communication Details
            $table->string('channel'); // email, slack, meeting, phone, teams, etc.
            $table->string('subject')->nullable();
            $table->text('content');
            $table->json('metadata')->nullable(); // Additional channel-specific data

            // Communication Type and Context
            $table->string('communication_type'); // notification, discussion, approval_request, status_update, escalation, etc.
            $table->string('direction'); // inbound, outbound, internal
            $table->string('priority')->default('medium'); // low, medium, high, urgent

            // Tracking and Outcomes
            $table->timestamp('communication_date'); // When the communication occurred
            $table->string('status')->default('sent'); // sent, delivered, read, responded, failed
            $table->text('outcome_summary')->nullable();
            $table->json('follow_up_actions')->nullable(); // Tasks or actions resulting from communication
            $table->timestamp('follow_up_due_date')->nullable();

            // Integration Data
            $table->string('external_id')->nullable(); // ID from external system (email ID, Slack message ID, etc.)
            $table->string('thread_id')->nullable(); // For grouping related communications
            $table->json('attachments')->nullable(); // File references or attachment metadata

            // Audit and Compliance
            $table->boolean('is_sensitive')->default(false);
            $table->string('compliance_tags')->nullable(); // GDPR, SOX, etc.
            $table->text('retention_policy')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['release_id', 'communication_date']);
            $table->index(['channel', 'communication_type']);
            $table->index(['communication_date']);
            $table->index(['status']);
            $table->index(['thread_id']);
            $table->index(['external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
