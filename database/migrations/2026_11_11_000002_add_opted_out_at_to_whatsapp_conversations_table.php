<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records that a contact asked to stop receiving messages (tugas 11 Nov).
     *
     * Stored on the conversation rather than in a separate `opt_outs` table
     * because it is the same key (tenant + contact number) and the same lifetime.
     *
     * `opted_out_at` is a timestamp, not a boolean: when a customer disputes a
     * message, "when did they opt out" is the question that matters. It is never
     * cleared automatically — re-subscribing must be an explicit action by the
     * customer, not an inference from a later message.
     */
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->timestamp('opted_out_at')->nullable()->after('lead_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn('opted_out_at');
        });
    }
};
