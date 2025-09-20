<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear existing stakeholder-releases data since we're changing the structure
        DB::table('stakeholder_releases')->delete();

        // Remove stakeholder fields from users table since we have a dedicated stakeholders table now
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'title', 'company', 'department', 'phone', 'slack_handle', 'teams_handle',
                'preferred_communication_channel', 'communication_frequency',
                'tags', 'stakeholder_notes', 'last_contact_at', 'last_contact_channel',
                'influence_level', 'support_level', 'timezone', 'is_available', 'unavailable_until'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add stakeholder fields to users table
        Schema::table('users', function (Blueprint $table) {
            // Contact information
            $table->string('title')->nullable()->after('name');
            $table->string('company')->nullable()->after('title');
            $table->string('department')->nullable()->after('company');
            $table->string('phone')->nullable()->after('email');
            $table->string('slack_handle')->nullable()->after('phone');
            $table->string('teams_handle')->nullable()->after('slack_handle');

            // Communication preferences
            $table->enum('preferred_communication_channel', ['email', 'slack', 'teams', 'phone'])
                  ->default('email')
                  ->after('teams_handle');
            $table->enum('communication_frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'as_needed'])
                  ->default('as_needed')
                  ->after('preferred_communication_channel');

            // Stakeholder context
            $table->json('tags')->nullable()->after('communication_frequency');
            $table->text('stakeholder_notes')->nullable()->after('tags');
            $table->timestamp('last_contact_at')->nullable()->after('stakeholder_notes');
            $table->string('last_contact_channel')->nullable()->after('last_contact_at');

            // Influence and support mapping
            $table->enum('influence_level', ['low', 'medium', 'high'])->nullable()->after('last_contact_channel');
            $table->enum('support_level', ['low', 'medium', 'high'])->nullable()->after('influence_level');

            // Availability and timezone
            $table->string('timezone')->nullable()->after('support_level');
            $table->boolean('is_available')->default(true)->after('timezone');
            $table->date('unavailable_until')->nullable()->after('is_available');

            // Indexes for performance
            $table->index('last_contact_at');
            $table->index(['influence_level', 'support_level']);
            $table->index('preferred_communication_channel');
        });
    }
};
