<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks an inbound message as handled by the bot (B-3, tugas 10 Nov).
     *
     * A queued job can be retried after a timeout, and a retried reply job that
     * does not check this would answer the same customer twice. The column is also
     * what makes "was this message processed?" inspectable from the database
     * instead of inferred from the conversation state.
     */
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });
    }
};
