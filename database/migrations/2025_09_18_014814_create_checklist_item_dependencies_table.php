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
        Schema::create('checklist_item_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prerequisite_assignment_id')->constrained('checklist_item_assignments')->onDelete('cascade');
            $table->foreignId('dependent_assignment_id')->constrained('checklist_item_assignments')->onDelete('cascade');
            $table->enum('dependency_type', ['blocks', 'enables', 'informs'])->default('blocks');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['prerequisite_assignment_id', 'is_active']);
            $table->index(['dependent_assignment_id', 'is_active']);
            $table->index(['dependency_type', 'is_active']);

            // Prevent duplicate dependencies
            $table->unique(['prerequisite_assignment_id', 'dependent_assignment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_item_dependencies');
    }
};
