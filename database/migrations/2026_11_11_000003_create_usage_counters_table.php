<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant usage for a billing period (B-3 tugas 11 Nov, B-2 nanti).
     *
     * The shape follows the design agreed in
     * docs/implementation/phase-04-plan-dan-entitlement.md §4.5 so the billing
     * workstream can use the same table for `users` and AI generations instead of
     * inventing a second counter.
     *
     * `limit` is copied from `config('plans.plans.{code}.limits')` when the period
     * starts: raising a plan mid-month then applies from the next period, and the
     * number that was actually enforced stays readable afterwards.
     */
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 64);
            $table->string('period', 7);
            $table->unsignedInteger('used')->default(0);
            $table->unsignedInteger('limit')->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'metric', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
