<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every verified payment notification, once (B-2, tugas 24 Nov).
     *
     * The table is what makes the webhook idempotent: a retried delivery maps to the
     * same row (provider + order + transaction + raw status), and a row that was
     * already processed is answered 200 without touching money again.
     *
     * `processed_at` means "a final decision was made for this delivery" — including
     * a rejection such as an amount mismatch, which must not be retried forever.
     * A processing *failure* leaves it null and stores `error`, so the provider's
     * retry can try again.
     *
     * Deliberately not tenant-owned: a notification can name an order id that does
     * not exist (another system, a sandbox probe), and it still has to be recorded.
     */
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('order_id', 40);
            $table->string('transaction_id', 64)->nullable();
            $table->string('transaction_status', 32);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['provider', 'order_id']);
            // Belt and braces next to the application-level dedupe: two identical
            // deliveries can never become two rows.
            $table->unique(['provider', 'order_id', 'transaction_id', 'transaction_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
