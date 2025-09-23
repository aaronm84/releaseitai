<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds critical performance indexes for the feedback learning system
     * based on TDD test requirements and query patterns identified in the test suite.
     */
    public function up(): void
    {
        // ========================================
        // FEEDBACK TABLE CRITICAL INDEXES
        // ========================================

        Schema::table('feedback', function (Blueprint $table) {
            // Analytics and aggregation composite index - most critical for performance
            // Supports queries like: WHERE user_id = ? AND action = ? AND confidence >= ?
            $table->index(['user_id', 'action', 'confidence'], 'idx_feedback_user_action_confidence');

            // Date range queries with action filtering for timeline analysis
            // Supports queries like: WHERE created_at BETWEEN ? AND ? ORDER BY created_at
            $table->index(['created_at', 'action'], 'idx_feedback_created_action');

            // Signal type filtering for passive vs explicit feedback analysis
            // Supports queries like: WHERE signal_type = 'passive' AND confidence > 0.5
            $table->index(['signal_type', 'confidence'], 'idx_feedback_signal_confidence');

            // Output-based feedback aggregation (already exists but ensuring for performance)
            // This supports the aggregateFeedbackPatterns queries in FeedbackService
            if (!Schema::hasIndex('feedback', ['output_id', 'action'])) {
                $table->index(['output_id', 'action'], 'idx_feedback_output_action');
            }

            // User preference learning queries
            // Supports queries like: WHERE user_id = ? AND type = 'inline' ORDER BY created_at DESC
            if (!Schema::hasIndex('feedback', ['user_id', 'type'])) {
                $table->index(['user_id', 'type'], 'idx_feedback_user_type');
            }
        });

        // Add PostgreSQL-specific GIN index for JSON metadata queries
        if (DB::getDriverName() === 'pgsql') {
            // GIN index for fast JSON key/value searches in feedback metadata
            // Note: Cast JSON to JSONB for better performance with GIN indexes
            // Supports queries like: WHERE metadata::jsonb @> '{"edit_reason": "Added stakeholder input"}'
            DB::statement('CREATE INDEX IF NOT EXISTS idx_feedback_metadata_gin ON feedback USING GIN ((metadata::jsonb))');

            // Partial index for high confidence feedback only (confidence >= 0.8)
            // Reduces index size and improves performance for quality analysis
            DB::statement('CREATE INDEX IF NOT EXISTS idx_feedback_high_confidence ON feedback (user_id, action, created_at) WHERE confidence >= 0.8');
        }

        // ========================================
        // EMBEDDINGS TABLE PERFORMANCE INDEXES
        // ========================================

        Schema::table('embeddings', function (Blueprint $table) {
            // Vector similarity search optimization
            // Supports model-specific vector searches for better performance
            $table->index(['model', 'dimensions'], 'idx_embeddings_model_dimensions');

            // Content type filtering with creation time for temporal analysis
            // Supports queries like: WHERE content_type = 'App\Models\Input' ORDER BY created_at DESC
            $table->index(['content_type', 'created_at'], 'idx_embeddings_type_created');
        });

        // ========================================
        // INPUTS TABLE ANALYTICS INDEXES
        // ========================================

        Schema::table('inputs', function (Blueprint $table) {
            // Multi-dimensional filtering for input analysis
            // Supports queries like: WHERE type = 'brain_dump' AND source = 'manual_entry' ORDER BY created_at DESC
            $table->index(['type', 'source', 'created_at'], 'idx_inputs_type_source_created');
        });

        // Add PostgreSQL-specific GIN index for inputs metadata
        if (DB::getDriverName() === 'pgsql') {
            // GIN index for JSON metadata searches in inputs
            // Note: Cast JSON to JSONB for better performance with GIN indexes
            // Supports queries like: WHERE metadata::jsonb @> '{"user_context": "release_planning"}'
            DB::statement('CREATE INDEX IF NOT EXISTS idx_inputs_metadata_gin ON inputs USING GIN ((metadata::jsonb))');
        }

        // ========================================
        // OUTPUTS TABLE PERFORMANCE INDEXES
        // ========================================

        Schema::table('outputs', function (Blueprint $table) {
            // Quality and feedback analysis composite index
            // Supports queries like: WHERE quality_score >= ? AND feedback_integrated = true
            $table->index(['quality_score', 'feedback_integrated'], 'idx_outputs_quality_feedback');

            // Version tracking with parent relationship analysis
            // Supports queries for output evolution tracking
            $table->index(['parent_output_id', 'version'], 'idx_outputs_parent_version');

            // AI model performance analysis
            // Supports queries like: WHERE ai_model = 'claude-3-5-sonnet' AND quality_score >= 0.8
            $table->index(['ai_model', 'quality_score', 'created_at'], 'idx_outputs_model_quality_created');
        });

        // Add PostgreSQL-specific GIN index for outputs metadata
        if (DB::getDriverName() === 'pgsql') {
            // GIN index for JSON metadata searches in outputs
            // Note: Cast JSON to JSONB for better performance with GIN indexes
            // Supports queries like: WHERE metadata::jsonb @> '{"confidence": 0.87}'
            DB::statement('CREATE INDEX IF NOT EXISTS idx_outputs_metadata_gin ON outputs USING GIN ((metadata::jsonb))');
        }

        // ========================================
        // WORKSTREAM HIERARCHY OPTIMIZATION INDEXES
        // (Adding missing indexes not covered by existing migrations)
        // ========================================

        Schema::table('workstreams', function (Blueprint $table) {
            // Hierarchy depth filtering optimization
            // Supports queries like: WHERE hierarchy_depth = 0 (root workstreams)
            if (!Schema::hasIndex('workstreams', ['hierarchy_depth'])) {
                $table->index(['hierarchy_depth'], 'idx_workstreams_hierarchy_depth');
            }

            // Combined hierarchy traversal optimization
            // Supports queries like: WHERE parent_workstream_id = ? AND hierarchy_depth < ?
            if (!Schema::hasIndex('workstreams', ['parent_workstream_id', 'hierarchy_depth'])) {
                $table->index(['parent_workstream_id', 'hierarchy_depth'], 'idx_workstreams_parent_depth');
            }
        });

        // Add PostgreSQL-specific partial index for root workstreams
        if (DB::getDriverName() === 'pgsql') {
            // Partial index for root workstreams (parent_workstream_id IS NULL)
            // Significantly reduces index size for hierarchy queries
            DB::statement('CREATE INDEX IF NOT EXISTS idx_workstreams_root_only ON workstreams (id, type, status) WHERE parent_workstream_id IS NULL');
        }

        // ========================================
        // RELEASE AND STAKEHOLDER OPTIMIZATION
        // ========================================

        if (Schema::hasTable('releases')) {
            Schema::table('releases', function (Blueprint $table) {
                // Release timeline optimization
                // Supports queries like: WHERE target_date BETWEEN ? AND ? AND status IN (...)
                if (!Schema::hasIndex('releases', ['target_date', 'status'])) {
                    $table->index(['target_date', 'status'], 'idx_releases_target_status');
                }
            });
        }

        if (Schema::hasTable('stakeholder_releases')) {
            Schema::table('stakeholder_releases', function (Blueprint $table) {
                // Stakeholder-release pivot table optimization
                // Supports both directions of the many-to-many relationship efficiently
                if (!Schema::hasIndex('stakeholder_releases', ['stakeholder_id', 'release_id'])) {
                    $table->index(['stakeholder_id', 'release_id'], 'idx_stakeholder_releases_stakeholder_release');
                }
                if (!Schema::hasIndex('stakeholder_releases', ['release_id', 'stakeholder_id'])) {
                    $table->index(['release_id', 'stakeholder_id'], 'idx_stakeholder_releases_release_stakeholder');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Drops all indexes created by this migration in reverse order.
     */
    public function down(): void
    {
        // Drop PostgreSQL-specific indexes first
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_feedback_metadata_gin');
            DB::statement('DROP INDEX IF EXISTS idx_feedback_high_confidence');
            DB::statement('DROP INDEX IF EXISTS idx_inputs_metadata_gin');
            DB::statement('DROP INDEX IF EXISTS idx_outputs_metadata_gin');
            DB::statement('DROP INDEX IF EXISTS idx_workstreams_root_only');
        }

        // Drop stakeholder_releases indexes if table exists
        if (Schema::hasTable('stakeholder_releases')) {
            Schema::table('stakeholder_releases', function (Blueprint $table) {
                if (Schema::hasIndex('stakeholder_releases', 'idx_stakeholder_releases_stakeholder_release')) {
                    $table->dropIndex('idx_stakeholder_releases_stakeholder_release');
                }
                if (Schema::hasIndex('stakeholder_releases', 'idx_stakeholder_releases_release_stakeholder')) {
                    $table->dropIndex('idx_stakeholder_releases_release_stakeholder');
                }
            });
        }

        // Drop releases indexes if table exists
        if (Schema::hasTable('releases')) {
            Schema::table('releases', function (Blueprint $table) {
                if (Schema::hasIndex('releases', 'idx_releases_target_status')) {
                    $table->dropIndex('idx_releases_target_status');
                }
            });
        }

        // Drop workstreams indexes
        Schema::table('workstreams', function (Blueprint $table) {
            if (Schema::hasIndex('workstreams', 'idx_workstreams_hierarchy_depth')) {
                $table->dropIndex('idx_workstreams_hierarchy_depth');
            }
            if (Schema::hasIndex('workstreams', 'idx_workstreams_parent_depth')) {
                $table->dropIndex('idx_workstreams_parent_depth');
            }
        });

        // Drop outputs indexes
        Schema::table('outputs', function (Blueprint $table) {
            if (Schema::hasIndex('outputs', 'idx_outputs_quality_feedback')) {
                $table->dropIndex('idx_outputs_quality_feedback');
            }
            if (Schema::hasIndex('outputs', 'idx_outputs_parent_version')) {
                $table->dropIndex('idx_outputs_parent_version');
            }
            if (Schema::hasIndex('outputs', 'idx_outputs_model_quality_created')) {
                $table->dropIndex('idx_outputs_model_quality_created');
            }
        });

        // Drop inputs indexes
        Schema::table('inputs', function (Blueprint $table) {
            if (Schema::hasIndex('inputs', 'idx_inputs_type_source_created')) {
                $table->dropIndex('idx_inputs_type_source_created');
            }
        });

        // Drop embeddings indexes
        Schema::table('embeddings', function (Blueprint $table) {
            if (Schema::hasIndex('embeddings', 'idx_embeddings_model_dimensions')) {
                $table->dropIndex('idx_embeddings_model_dimensions');
            }
            if (Schema::hasIndex('embeddings', 'idx_embeddings_type_created')) {
                $table->dropIndex('idx_embeddings_type_created');
            }
        });

        // Drop feedback indexes
        Schema::table('feedback', function (Blueprint $table) {
            if (Schema::hasIndex('feedback', 'idx_feedback_user_action_confidence')) {
                $table->dropIndex('idx_feedback_user_action_confidence');
            }
            if (Schema::hasIndex('feedback', 'idx_feedback_created_action')) {
                $table->dropIndex('idx_feedback_created_action');
            }
            if (Schema::hasIndex('feedback', 'idx_feedback_signal_confidence')) {
                $table->dropIndex('idx_feedback_signal_confidence');
            }

            // Only drop these if they exist and were created by this migration
            // (checking prevents errors if they were created by the original migration)
            if (Schema::hasIndex('feedback', 'idx_feedback_output_action')) {
                $table->dropIndex('idx_feedback_output_action');
            }
            if (Schema::hasIndex('feedback', 'idx_feedback_user_type')) {
                $table->dropIndex('idx_feedback_user_type');
            }
        });
    }
};