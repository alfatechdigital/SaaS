<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant lifecycle switch (Fase 2, tugas 2.3).
     *
     * A nullable timestamp instead of a status enum: "not suspended" is the
     * absence of a value, reverting an accidental suspension only clears one
     * column, and the moment of suspension stays available for the operator.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('public_page_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });
    }
};
