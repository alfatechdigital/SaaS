<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant add-ons (PDR-06, B-2 tugas 26 Nov).
     *
     * An add-on buys one feature that the tenant's plan does not include; it never
     * changes the plan label (phase-04 §4.5.6). "Active" is time-based — `ends_at`
     * null or in the future — so it cannot be part of a unique index. Instead the
     * pair `(team_id, feature)` is unique: granting again extends the existing row
     * rather than stacking a second entitlement nobody can tell apart.
     *
     * `provider_reference` is nullable on purpose: today an operator grants an
     * add-on from the platform panel, while a future self-service purchase (gelombang
     * 2) will store its payment reference here.
     */
    public function up(): void
    {
        Schema::create('tenant_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 64);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'feature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_addons');
    }
};
