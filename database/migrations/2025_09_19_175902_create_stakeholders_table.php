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
        Schema::create('stakeholders', function (Blueprint $table) {
            $table->id();

            // Multi-tenant isolation - required foreign key to users table
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Required contact information
            $table->string('name');
            $table->string('email');

            // Contact details
            $table->string('company')->nullable();
            $table->string('title')->nullable();
            $table->string('department')->nullable();
            $table->string('phone')->nullable();
            $table->string('linkedin_handle')->nullable();
            $table->string('twitter_handle')->nullable();
            $table->string('slack_handle')->nullable();
            $table->string('teams_handle')->nullable();

            // Communication preferences
            $table->enum('preferred_communication_channel', [
                'email', 'slack', 'teams', 'phone', 'linkedin', 'twitter'
            ])->nullable();
            $table->enum('communication_frequency', [
                'immediate', 'daily', 'weekly', 'monthly', 'quarterly', 'as_needed'
            ])->default('as_needed');

            // Stakeholder context and analysis
            $table->json('tags')->nullable();
            $table->enum('influence_level', ['low', 'medium', 'high'])->nullable();
            $table->enum('support_level', ['low', 'medium', 'high'])->nullable();
            $table->text('notes')->nullable();
            $table->text('stakeholder_notes')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('needs_follow_up')->default(false);
            $table->string('timezone')->nullable();
            $table->timestamp('unavailable_until')->nullable();

            // Last contact tracking
            $table->timestamp('last_contact_at')->nullable();
            $table->enum('last_contact_channel', [
                'email', 'slack', 'teams', 'phone', 'linkedin', 'twitter', 'in_person', 'other'
            ])->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('email');
            $table->index('last_contact_at');
            $table->index('influence_level');
            $table->index('support_level');
            $table->index(['influence_level', 'support_level']);

            // Unique constraint for email per user (multi-tenant)
            $table->unique(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stakeholders');
    }
};
