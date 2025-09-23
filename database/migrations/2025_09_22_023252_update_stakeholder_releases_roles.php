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
        // Update the enum to include the new role types
        DB::statement("ALTER TABLE stakeholder_releases DROP CONSTRAINT stakeholder_releases_role_check");
        DB::statement("ALTER TABLE stakeholder_releases ADD CONSTRAINT stakeholder_releases_role_check CHECK (role IN ('viewer', 'reviewer', 'approver', 'manager', 'owner', 'observer'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE stakeholder_releases DROP CONSTRAINT stakeholder_releases_role_check");
        DB::statement("ALTER TABLE stakeholder_releases ADD CONSTRAINT stakeholder_releases_role_check CHECK (role IN ('owner', 'reviewer', 'approver', 'observer'))");
    }
};
