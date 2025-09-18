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
        Schema::create('communication_participants', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('communication_id')->constrained('communications')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Participant Details
            $table->string('participant_type'); // to, cc, bcc, attendee, optional_attendee
            $table->string('role')->nullable(); // sender, recipient, moderator, presenter

            // Engagement Tracking
            $table->string('delivery_status')->default('pending'); // pending, delivered, read, responded, failed
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            // Response Data
            $table->text('response_content')->nullable();
            $table->string('response_sentiment')->nullable(); // positive, negative, neutral

            // Channel-specific Data
            $table->string('contact_method')->nullable(); // email address, slack handle, phone number used
            $table->json('channel_metadata')->nullable(); // Channel-specific participant data

            $table->timestamps();

            // Unique constraint to prevent duplicate participants per communication
            $table->unique(['communication_id', 'user_id', 'participant_type']);

            // Indexes for performance
            $table->index(['communication_id']);
            $table->index(['user_id']);
            $table->index(['participant_type']);
            $table->index(['delivery_status']);
            $table->index(['delivered_at']);
            $table->index(['responded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_participants');
    }
};
