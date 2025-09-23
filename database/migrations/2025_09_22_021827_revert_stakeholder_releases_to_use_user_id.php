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
            // Drop foreign key constraint for stakeholder_id
            $table->dropForeign(['stakeholder_id']);

            // Drop constraints and indexes with stakeholder_id
            $table->dropUnique(['stakeholder_id', 'release_id']);
            $table->dropIndex(['stakeholder_id', 'role']);

            // Drop stakeholder_id column
            $table->dropColumn('stakeholder_id');

            // Re-add user_id column with foreign key constraint
            $table->foreignId('user_id')->after('id')->constrained('users')->onDelete('cascade');

            // Re-add original constraints and indexes
            $table->unique(['user_id', 'release_id']);
            $table->index(['user_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stakeholder_releases', function (Blueprint $table) {
            // Drop foreign key constraint for user_id
            $table->dropForeign(['user_id']);

            // Drop unique constraint that includes user_id
            $table->dropUnique(['user_id', 'release_id']);

            // Drop composite indexes that include user_id
            $table->dropIndex(['user_id', 'role']);

            // Drop the user_id column
            $table->dropColumn('user_id');

            // Add stakeholder_id column with foreign key constraint
            $table->foreignId('stakeholder_id')->after('id')->constrained('stakeholders')->onDelete('cascade');

            // Re-add constraints and indexes with stakeholder_id
            $table->unique(['stakeholder_id', 'release_id']);
            $table->index(['stakeholder_id', 'role']);
        });
    }
};
