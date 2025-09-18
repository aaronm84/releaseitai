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
        Schema::create('workstream_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstream_id')->constrained('workstreams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('permission_type', ['view', 'edit', 'admin']);
            $table->enum('scope', ['workstream_only', 'workstream_and_children'])->default('workstream_only');
            $table->foreignId('granted_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->unique(['workstream_id', 'user_id', 'permission_type']);
            $table->index(['workstream_id', 'permission_type']);
            $table->index(['user_id', 'permission_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workstream_permissions');
    }
};
