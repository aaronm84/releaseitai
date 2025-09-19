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
        Schema::table('workstreams', function (Blueprint $table) {
            // Add hierarchy depth column for performance optimization
            $table->unsignedTinyInteger('hierarchy_depth')->default(1)->after('parent_workstream_id');

            // Add index for depth-based queries
            $table->index(['hierarchy_depth', 'status'], 'idx_workstreams_depth_status');
        });

        // Populate hierarchy_depth for existing records
        \DB::statement("
            WITH RECURSIVE workstream_hierarchy AS (
                -- Base case: root workstreams (no parent)
                SELECT id, parent_workstream_id, 1 as depth
                FROM workstreams
                WHERE parent_workstream_id IS NULL

                UNION ALL

                -- Recursive case: children of previous level
                SELECT w.id, w.parent_workstream_id, wh.depth + 1 as depth
                FROM workstreams w
                INNER JOIN workstream_hierarchy wh ON w.parent_workstream_id = wh.id
            )
            UPDATE workstreams
            SET hierarchy_depth = (
                SELECT depth
                FROM workstream_hierarchy
                WHERE workstream_hierarchy.id = workstreams.id
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workstreams', function (Blueprint $table) {
            $table->dropIndex('idx_workstreams_depth_status');
            $table->dropColumn('hierarchy_depth');
        });
    }
};
