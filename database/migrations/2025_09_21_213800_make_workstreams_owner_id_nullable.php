<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the trigger temporarily
        DB::statement('DROP TRIGGER IF EXISTS workstream_ownership_change_trigger ON workstreams');

        Schema::table('workstreams', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->change();
        });

        // Recreate the trigger
        DB::statement('
            CREATE TRIGGER workstream_ownership_change_trigger
            AFTER UPDATE OF owner_id ON workstreams
            FOR EACH ROW
            WHEN (OLD.owner_id IS DISTINCT FROM NEW.owner_id)
            EXECUTE FUNCTION prevent_orphaned_workstreams()
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workstreams', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable(false)->change();
        });
    }
};
