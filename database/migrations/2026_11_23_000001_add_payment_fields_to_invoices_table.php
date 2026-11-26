<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Snap artifacts of a payment link (B-2, tugas 23 Nov).
     *
     * Kept on the invoice so the same link can be shown again: a tenant that
     * reloads the page must see the transaction it already has instead of opening
     * a second one for the same period. `payment_token` is what Snap.js needs;
     * `payment_url` is the hosted redirect.
     *
     * A separate migration rather than an edit of `create_invoices_table`, because
     * that migration is already committed and may have run somewhere.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_token')->nullable();
            $table->string('payment_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_token', 'payment_url']);
        });
    }
};
