<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link a project back to the lead it was converted from.
     *
     * The unique index is what actually prevents converting the same lead twice:
     * `LeadController@convert` also checks first so the user gets a friendly
     * message instead of a constraint violation, but the database is the
     * authority. SQLite and MySQL both treat NULLs as distinct in a unique
     * index, so projects without a source lead are unaffected.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('lead_id')
                ->nullable()
                ->after('team_id')
                ->constrained('leads')
                ->nullOnDelete();

            $table->unique('lead_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['lead_id']);
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};
