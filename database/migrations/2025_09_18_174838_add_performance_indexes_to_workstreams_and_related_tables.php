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
        // Add composite indexes for workstreams hierarchy operations
        Schema::table('workstreams', function (Blueprint $table) {
            // Composite index for parent-child hierarchy traversal
            $table->index(['parent_workstream_id', 'status'], 'idx_workstreams_parent_status');

            // Composite index for hierarchy depth calculations
            $table->index(['parent_workstream_id', 'id'], 'idx_workstreams_parent_id');

            // Index for type-based filtering with status
            $table->index(['type', 'status'], 'idx_workstreams_type_status');

            // Index for owner-based queries with status
            $table->index(['owner_id', 'status'], 'idx_workstreams_owner_status');
        });

        // Add indexes for workstream permissions
        Schema::table('workstream_permissions', function (Blueprint $table) {
            // Composite index for permission inheritance queries
            $table->index(['workstream_id', 'user_id', 'permission_type'], 'idx_permissions_workstream_user_type');

            // Index for scope-based inheritance
            $table->index(['workstream_id', 'scope'], 'idx_permissions_workstream_scope');

            // Index for user permission lookups
            $table->index(['user_id', 'permission_type', 'scope'], 'idx_permissions_user_type_scope');
        });

        // Add indexes for releases
        Schema::table('releases', function (Blueprint $table) {
            // Composite index for workstream-based queries with status
            $table->index(['workstream_id', 'status'], 'idx_releases_workstream_status');

            // Index for target date queries
            $table->index(['target_date', 'status'], 'idx_releases_target_date_status');
        });

        // Add indexes for checklist item assignments
        Schema::table('checklist_item_assignments', function (Blueprint $table) {
            // Composite index for release-based aggregations
            $table->index(['release_id', 'status'], 'idx_assignments_release_status');

            // Index for assignee-based queries
            $table->index(['assignee_id', 'status'], 'idx_assignments_assignee_status');

            // Index for due date monitoring
            $table->index(['due_date', 'status'], 'idx_assignments_due_date_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workstreams', function (Blueprint $table) {
            $table->dropIndex('idx_workstreams_parent_status');
            $table->dropIndex('idx_workstreams_parent_id');
            $table->dropIndex('idx_workstreams_type_status');
            $table->dropIndex('idx_workstreams_owner_status');
        });

        Schema::table('workstream_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_permissions_workstream_user_type');
            $table->dropIndex('idx_permissions_workstream_scope');
            $table->dropIndex('idx_permissions_user_type_scope');
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('idx_releases_workstream_status');
            $table->dropIndex('idx_releases_target_date_status');
        });

        Schema::table('checklist_item_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_assignments_release_status');
            $table->dropIndex('idx_assignments_assignee_status');
            $table->dropIndex('idx_assignments_due_date_status');
        });
    }
};
