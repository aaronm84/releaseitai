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
        Schema::table('users', function (Blueprint $table) {
            $table->string('title')->nullable()->after('email');
            $table->string('company')->nullable()->after('title');
            $table->string('department')->nullable()->after('company');
            $table->string('phone')->nullable()->after('department');
            $table->string('slack_handle')->nullable()->after('phone');
            $table->string('teams_handle')->nullable()->after('slack_handle');
            $table->enum('preferred_communication_channel', ['email', 'slack', 'teams', 'phone'])->default('email')->after('teams_handle');
            $table->enum('communication_frequency', ['daily', 'weekly', 'as_needed'])->default('as_needed')->after('preferred_communication_channel');
            $table->json('tags')->nullable()->after('communication_frequency');
            $table->text('stakeholder_notes')->nullable()->after('tags');
            $table->timestamp('last_contact_at')->nullable()->after('stakeholder_notes');
            $table->string('last_contact_channel')->nullable()->after('last_contact_at');
            $table->enum('influence_level', ['low', 'medium', 'high'])->default('medium')->after('last_contact_channel');
            $table->enum('support_level', ['opponent', 'neutral', 'supporter', 'champion'])->default('neutral')->after('influence_level');
            $table->string('timezone')->nullable()->after('support_level');
            $table->boolean('is_available')->default(true)->after('timezone');
            $table->date('unavailable_until')->nullable()->after('is_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'company',
                'department',
                'phone',
                'slack_handle',
                'teams_handle',
                'preferred_communication_channel',
                'communication_frequency',
                'tags',
                'stakeholder_notes',
                'last_contact_at',
                'last_contact_channel',
                'influence_level',
                'support_level',
                'timezone',
                'is_available',
                'unavailable_until',
            ]);
        });
    }
};