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
        Schema::create('action_item_workstreams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_item_id')->constrained('content_action_items')->onDelete('cascade');
            $table->foreignId('workstream_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['action_item_id', 'workstream_id']);

            // Indexes for performance
            $table->index('action_item_id');
            $table->index('workstream_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_item_workstreams');
    }
};
