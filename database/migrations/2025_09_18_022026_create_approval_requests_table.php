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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->onDelete('cascade');
            $table->string('approval_type'); // legal, security, design, technical
            $table->foreignId('approver_id')->constrained('users')->onDelete('cascade');
            $table->text('description');
            $table->date('due_date');
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('pending'); // pending, approved, rejected, needs_changes, cancelled, expired
            $table->text('cancellation_reason')->nullable();
            $table->integer('reminder_count')->default(0);
            $table->timestamp('last_reminder_sent')->nullable();
            $table->integer('auto_expire_days')->default(30);
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['release_id', 'status']);
            $table->index(['approver_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('approval_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
