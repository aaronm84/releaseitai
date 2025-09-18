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
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('workstream_id')->constrained()->onDelete('cascade');
            $table->date('target_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled', 'on_hold'])->default('planned');
            $table->timestamps();

            $table->index(['workstream_id']);
            $table->index(['status']);
            $table->index(['target_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
