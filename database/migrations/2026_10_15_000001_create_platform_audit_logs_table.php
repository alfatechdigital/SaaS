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
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();

            // Deliberately no `team_id`: an operator acts *across* tenants, and
            // the row has to stay readable after the tenant itself is gone. That
            // is also why this table is kept apart from the tenant-scoped
            // `activity_logs` instead of being folded into it (tugas 2.4.3).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type');
            // Not a foreign key: the target type varies per row.
            $table->string('target_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Append-only: no `updated_at` is maintained.
            $table->timestamp('created_at')->nullable();

            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
    }
};
