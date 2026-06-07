<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add is_admin flag to users.
 *
 * All pre-existing users are backfilled to is_admin = true so that
 * existing single-admin installs continue working unchanged.
 * New OIDC-provisioned accounts start with the default (false) and
 * receive admin only through OIDC_ADMIN_GROUP membership or manual grant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('remember_token');
        });

        // Backfill: every user created before this migration is an admin.
        // This ensures existing single-admin deployments are not broken.
        DB::table('users')->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
