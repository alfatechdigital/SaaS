<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Delivery tracking for outbound WhatsApp messages (B-3, tugas 11 Nov).
     *
     * Meta reports `sent`/`delivered`/`read`/`failed` as separate webhooks that
     * arrive minutes apart, so each transition needs its own timestamp: without
     * them, "delivered 3 minutes after sending" cannot be told from "delivered an
     * hour later", and a failure has no visible cause.
     *
     * `status` already exists from 09 Nov; these columns are the audit trail
     * behind it, not a second source of truth.
     */
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->timestamp('failed_at')->nullable()->after('read_at');
            $table->string('failure_reason', 500)->nullable()->after('failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'read_at', 'failed_at', 'failure_reason']);
        });
    }
};
