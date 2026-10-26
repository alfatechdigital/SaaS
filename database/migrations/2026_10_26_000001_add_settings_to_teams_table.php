<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant preferences (Fase 2, tugas 2.5, keputusan D-04).
     *
     * A JSON column instead of a `tenant_settings` table: the current need is a
     * handful of scalar preferences, and a table would add a join plus a row per
     * tenant per key before anything queries them. Promote to a table once a
     * preference has to be filtered or indexed.
     *
     * Nullable, so every existing tenant keeps the application defaults without a
     * backfill.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
