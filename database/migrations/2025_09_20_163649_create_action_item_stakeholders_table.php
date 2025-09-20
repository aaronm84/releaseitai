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
        Schema::create('action_item_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_item_id')->constrained('content_action_items')->onDelete('cascade');
            $table->foreignId('stakeholder_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['assignee', 'reviewer', 'approver', 'stakeholder', 'informed'])->default('stakeholder');
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['action_item_id', 'stakeholder_id']);

            // Indexes for performance
            $table->index(['action_item_id', 'role']);
            $table->index('stakeholder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_item_stakeholders');
    }
};
