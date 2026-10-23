<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the starter kit's "personal team" flag.
     *
     * Onboarding is invitation-only (PDR-06), so no production path ever creates
     * a personal team — every `Team` is a tenant (ADR-02). The column only
     * existed to keep the starter kit's self-service signup assumption alive.
     *
     * No data is migrated on purpose: local databases are rebuilt with
     * `migrate:fresh --seed`. If an environment still holds rows with
     * `is_personal = 1` before this runs, clean them up first — once the column
     * is gone those rows silently become ordinary tenants.
     *
     * @see \docs\implementation\cleanup-personal-team.md
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_personal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('slug');
        });
    }
};
