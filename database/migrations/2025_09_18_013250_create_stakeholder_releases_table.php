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
        Schema::create('stakeholder_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('release_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['owner', 'reviewer', 'approver', 'observer']);
            $table->enum('notification_preference', ['email', 'slack', 'none'])->default('email');
            $table->timestamps();

            // Unique constraint to prevent duplicate stakeholder assignments
            $table->unique(['user_id', 'release_id']);

            // Indexes for performance
            $table->index(['release_id', 'role']);
            $table->index(['user_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stakeholder_releases');
    }
};
