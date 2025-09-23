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
        Schema::table('stakeholder_releases', function (Blueprint $table) {
            // Drop the unique constraint to allow multiple roles per user per release
            $table->dropUnique(['user_id', 'release_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stakeholder_releases', function (Blueprint $table) {
            // Re-add the unique constraint
            $table->unique(['user_id', 'release_id']);
        });
    }
};
