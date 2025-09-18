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
            $table->enum('type', ['product_line', 'initiative', 'experiment'])->default('initiative')->after('description');
            $table->foreignId('parent_workstream_id')->nullable()->constrained('workstreams')->onDelete('cascade')->after('type');
            $table->enum('status', ['draft', 'active', 'on_hold', 'completed', 'cancelled'])->default('active')->after('parent_workstream_id');

            $table->index(['parent_workstream_id']);
            $table->index(['type']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workstreams', function (Blueprint $table) {
            $table->dropForeign(['parent_workstream_id']);
            $table->dropIndex(['parent_workstream_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropColumn(['type', 'parent_workstream_id', 'status']);
        });
    }
};
