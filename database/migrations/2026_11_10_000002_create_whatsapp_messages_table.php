<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every WhatsApp message a tenant sends or receives (B-3, gelombang 1).
     *
     * One table for both directions, because they answer the same questions and
     * will be read side by side in the tenant's inbox: what was said, to whom, and
     * when. `direction` is the only thing that differs, and splitting it into two
     * tables would make "show me this conversation" a union.
     *
     * `wamid` is Meta's message id. It is unique because Meta retries a webhook
     * whenever it does not receive a 200 quickly, and a retry must update nothing —
     * a customer cannot receive the same message twice (see tugas 09 Nov).
     *
     * `provider_payload` keeps the raw object: the reply flow (10 Nov) and media
     * handling (11 Nov) need fields this table does not model yet, and re-fetching
     * them from Meta is not possible.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 8);
            $table->string('wamid')->nullable()->unique();
            $table->string('from_number', 32);
            $table->string('to_number', 32)->nullable();
            $table->string('type', 32)->default('text');
            $table->text('body')->nullable();
            $table->string('status', 16)->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
