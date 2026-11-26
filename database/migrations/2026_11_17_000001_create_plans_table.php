<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sellable plans (B-2, tugas 17 Nov).
     *
     * A copy of `config/plans.php` plus the commercial metadata billing owns —
     * never the source of entitlement: features and quotas stay in config
     * (ADR-14, D-06), so editing a price cannot change what a tenant may use.
     * The table exists so invoices and the platform panel (26 Nov) can read a
     * price, and so `subscriptions.plan_code` has a row with a name and a price
     * behind it. `PlanSeeder` copies both config files here.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            // Same key as `config('plans.plans')` — the only link between the two.
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            // Whole rupiah, no subunit — like every other money column (ADR-04).
            $table->unsignedBigInteger('price_monthly');
            $table->unsignedBigInteger('price_yearly')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
