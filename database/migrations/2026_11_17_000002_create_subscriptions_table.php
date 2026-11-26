<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One tenant's subscription (B-2, tugas 17 Nov).
     *
     * Shape follows the design agreed in
     * docs/implementation/phase-04-plan-dan-entitlement.md §4.5. `trialing` is
     * the only status a tenant gets without paying, and Midtrans is not a
     * subscription engine (D-05), so this table — not the provider — is the
     * source of truth for the lifecycle.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // Matches `plans.code` / `config('plans.plans')`; deliberately not a
            // foreign key: entitlement lives in config, and the plans table is a
            // projection that a fresh deployment has not necessarily seeded yet.
            $table->string('plan_code', 32);
            $table->string('status', 32);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            // When cancellation was requested. Cancelling takes effect at period
            // end (B.4.3), so this can be set while the subscription still runs.
            $table->timestamp('canceled_at')->nullable();
            $table->string('provider', 32);
            $table->string('provider_reference')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            /*
             * At most one *running* subscription per tenant, enforced by the
             * database rather than by whichever service happens to insert the
             * row. Status is not enough: a canceled subscription stays "running"
             * until its paid period closes. Neither MySQL nor PostgreSQL can
             * express that as a plain unique index (one needs a generated column,
             * the other a partial index), so a generated column that collapses to
             * NULL once `ended_at` is set gets a unique index — NULLs repeat
             * freely in all three engines.
             */
            $table->unsignedBigInteger('current_team_id')->nullable()
                ->storedAs('case when ended_at is null then team_id else null end');
            $table->unique('current_team_id');

            $table->index('plan_code');
            // The daily period check (tugas 19 Nov) scans by grace deadline.
            $table->index(['status', 'current_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
