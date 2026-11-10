<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per WhatsApp contact a tenant is talking to (B-3, tugas 10 Nov).
     *
     * The bot needs to remember where it is in a conversation between two
     * webhooks, and a webhook carries no session. State is therefore stored per
     * (tenant, contact number) — the natural key of a conversation.
     *
     * `lead_id` is the reason this table exists beyond the bot: when the small talk
     * is done, the contact becomes a lead. Keeping the link means a later inbound
     * message can be added to the same lead instead of creating a second one for
     * the same person.
     *
     * There is deliberately no `handed_over_at` boolean pair: `state` already has a
     * `handed_over` value, and two sources of truth for "is the bot replying" is
     * how a customer gets an automated answer after a human took over.
     */
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('contact_number', 32);
            $table->string('state', 32)->default('new');
            $table->string('contact_name')->nullable();
            $table->string('company_name')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'contact_number']);
            $table->index(['team_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
