<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subscription invoices (B-2, tugas 19 Nov).
     *
     * Midtrans bills one transaction at a time (D-05), so this table — not the
     * provider — is what says which period a payment belongs to. One invoice per
     * subscription per period is enforced here: a job that runs twice, or a
     * retried webhook, must never create a second bill for the same month.
     *
     * `reminders_sent` records dunning progress (three reminders in the 7-day
     * grace period of §B.4.3); the schedule itself lives in `config/billing.php`.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            // Deterministic, human-readable: INV-{period start}-{subscription id}.
            $table->string('number', 40)->unique();
            $table->string('status', 16);
            $table->string('billing_interval', 16);
            // Whole rupiah, no subunit (ADR-04).
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            // Due when the period starts; dunning counts days from here.
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('reminded_at')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            // Idempotency: one invoice per subscription per period (RSK-02).
            $table->unique(['subscription_id', 'period_start']);
            $table->index(['team_id', 'status']);
            // The period check scans unpaid invoices by due date.
            $table->index(['status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
