<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant WhatsApp Cloud API credentials (B-3, gelombang 1).
     *
     * Each tenant connects its own WhatsApp Business number, so the identity used
     * to send a message is tenant data — not deployment configuration.
     *
     * The access token is a secret, which is why it does not live in the
     * `settings` JSON column: that column is written through `setSetting()` and
     * read as plain preferences, so a token there would be one `dd()` away from a
     * log line. It gets its own column with an `encrypted` cast instead.
     *
     * Nullable, so every existing tenant stays "not connected" without a backfill.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('whatsapp_phone_number_id')->nullable()->after('settings');
            $table->string('whatsapp_business_account_id')->nullable()->after('whatsapp_phone_number_id');
            $table->text('whatsapp_access_token')->nullable()->after('whatsapp_business_account_id');
            $table->timestamp('whatsapp_connected_at')->nullable()->after('whatsapp_access_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id',
                'whatsapp_access_token',
                'whatsapp_connected_at',
            ]);
        });
    }
};
