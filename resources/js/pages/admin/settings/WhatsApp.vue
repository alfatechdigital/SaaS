<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    destroy as whatsappDestroy,
    update as whatsappUpdate,
} from '@/routes/whatsapp';

/**
 * The tenant's own WhatsApp Business number (B-3).
 *
 * Credentials live on the tenant, not in `.env`, so every tenant connects its own
 * number. The access token is never returned by the server: `has_token` only tells
 * this form whether a new one must be pasted, and a blank field keeps the stored
 * token.
 *
 * The form is usable even when the plan does not include `whatsapp_bot` — saving
 * credentials is configuration, while sending is gated by the sender itself. The
 * warning makes that difference visible instead of hiding the page.
 */
const props = defineProps<{
    whatsapp: {
        connected: boolean;
        has_token: boolean;
        phone_number_id: string | null;
        business_account_id: string | null;
        connected_at: string | null;
        driver: string;
        entitled: boolean;
        webhook_url: string;
    };
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const form = useForm({
    phone_number_id: props.whatsapp.phone_number_id ?? '',
    business_account_id: props.whatsapp.business_account_id ?? '',
    access_token: '',
});

const connectedSince = computed(() =>
    props.whatsapp.connected_at
        ? props.whatsapp.connected_at.slice(0, 10)
        : null,
);

const tokenHint = computed(() =>
    props.whatsapp.has_token
        ? 'Sudah tersimpan. Biarkan kosong untuk mempertahankan token yang ada.'
        : 'Permanent token dari System User Meta, bukan token sementara 24 jam.',
);

const inputClass =
    'w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none font-mono text-sm';
const labelClass =
    'block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1';
const cardClass =
    'bg-white dark:bg-slate-900 p-5 sm:p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-xs space-y-4';

function submit(): void {
    form.put(whatsappUpdate.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        onSuccess: () => form.reset('access_token'),
    });
}

function disconnect(): void {
    const confirmed = window.confirm(
        'Putuskan nomor WhatsApp ini? Token akses akan dihapus dari database.',
    );

    if (!confirmed) {
        return;
    }

    router.delete(whatsappDestroy.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan WhatsApp" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-xl font-bold text-slate-900 sm:text-2xl dark:text-slate-100"
            >
                WhatsApp Business
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Hubungkan nomor WhatsApp Business milik tenant ini. Pelanggan
                yang mengirim pesan ke nomor tersebut akan dilayani bot dan
                menjadi lead di CRM.
            </p>
        </div>

        <!-- Connection status -->
        <div :class="cardClass">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span
                        class="material-symbols-outlined text-[22px] text-emerald-600 dark:text-emerald-400"
                        >chat</span
                    >
                    <div>
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            Status Koneksi
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Kredensial disimpan terenkripsi di database tenant.
                        </p>
                    </div>
                </div>

                <span
                    :class="[
                        'rounded-full px-3 py-1 text-[11px] font-bold uppercase',
                        whatsapp.connected
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                    ]"
                >
                    {{ whatsapp.connected ? 'Terhubung' : 'Belum terhubung' }}
                </span>
            </div>

            <dl
                class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-3"
                v-if="whatsapp.connected"
            >
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <dt
                        class="text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500"
                    >
                        Phone Number ID
                    </dt>
                    <dd
                        class="mt-1 font-mono font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ whatsapp.phone_number_id }}
                    </dd>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <dt
                        class="text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500"
                    >
                        WABA ID
                    </dt>
                    <dd
                        class="mt-1 font-mono font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ whatsapp.business_account_id ?? '—' }}
                    </dd>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <dt
                        class="text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500"
                    >
                        Terhubung sejak
                    </dt>
                    <dd
                        class="mt-1 font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ connectedSince ?? '—' }}
                    </dd>
                </div>
            </dl>

            <div
                v-if="!whatsapp.entitled"
                class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <span class="material-symbols-outlined text-[18px]"
                    >warning</span
                >
                <span>
                    Paket aktif belum mencakup fitur WhatsApp Bot. Kredensial
                    tetap bisa disimpan, tetapi pesan tidak akan terkirim sampai
                    paket ditingkatkan.
                </span>
            </div>

            <div
                v-if="whatsapp.driver === 'log'"
                class="flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3.5 text-xs text-blue-900 dark:border-blue-800/60 dark:bg-blue-950/40 dark:text-blue-200"
            >
                <span class="material-symbols-outlined text-[18px]">info</span>
                <span>
                    Driver <strong>log</strong> sedang aktif di server ini:
                    pesan hanya dicatat di log, tidak dikirim ke WhatsApp. Untuk
                    pengiriman nyata, set
                    <code class="font-mono">WHATSAPP_DRIVER=meta</code>.
                </span>
            </div>
        </div>

        <!-- Credentials form -->
        <form :class="cardClass" @submit.prevent="submit">
            <div class="border-b border-slate-100 pb-4 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span
                        class="material-symbols-outlined text-[22px] text-blue-600 dark:text-blue-400"
                        >key</span
                    >
                    <div>
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            Kredensial Nomor
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Ambil nilainya dari Meta App → WhatsApp → API Setup.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label :class="labelClass" for="phone_number_id"
                        >Phone Number ID</label
                    >
                    <input
                        id="phone_number_id"
                        v-model="form.phone_number_id"
                        :class="inputClass"
                        inputmode="numeric"
                        placeholder="109999999999999"
                    />
                    <p
                        v-if="form.errors.phone_number_id"
                        class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.phone_number_id }}
                    </p>
                </div>

                <div>
                    <label :class="labelClass" for="business_account_id"
                        >WhatsApp Business Account ID
                        <span class="font-normal text-slate-400"
                            >(opsional)</span
                        ></label
                    >
                    <input
                        id="business_account_id"
                        v-model="form.business_account_id"
                        :class="inputClass"
                        inputmode="numeric"
                        placeholder="102290129340398"
                    />
                    <p
                        v-if="form.errors.business_account_id"
                        class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.business_account_id }}
                    </p>
                    <p
                        class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                    >
                        Dipakai untuk memastikan webhook benar-benar milik WABA
                        ini.
                    </p>
                </div>
            </div>

            <div>
                <label :class="labelClass" for="access_token"
                    >Permanent Access Token</label
                >
                <input
                    id="access_token"
                    v-model="form.access_token"
                    :class="inputClass"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="
                        whatsapp.has_token
                            ? '•••••••••••••••• (tidak diubah)'
                            : 'EAAG...'
                    "
                />
                <p
                    v-if="form.errors.access_token"
                    class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400"
                >
                    {{ form.errors.access_token }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                    {{ tokenHint }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800"
            >
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Nomor ini akan dipakai bot untuk membalas pelanggan.
                </p>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex items-center gap-2 rounded-xl bg-[#1e40af] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-blue-700 disabled:opacity-50 sm:text-sm"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >save</span
                    >
                    <span>{{
                        form.processing ? 'Menyimpan...' : 'Simpan & Hubungkan'
                    }}</span>
                </button>
            </div>
        </form>

        <!-- Webhook details -->
        <div :class="cardClass">
            <div class="flex items-center gap-2.5">
                <span
                    class="material-symbols-outlined text-[22px] text-indigo-600 dark:text-indigo-400"
                    >webhook</span
                >
                <div>
                    <h2
                        class="text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        Callback URL untuk Meta
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Isi di Meta App → WhatsApp → Configuration, lalu
                        subscribe field <code class="font-mono">messages</code>.
                    </p>
                </div>
            </div>

            <code
                class="block overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-3.5 font-mono text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200"
            >
                {{ whatsapp.webhook_url }}
            </code>
        </div>

        <!-- Disconnect -->
        <div
            v-if="whatsapp.connected"
            :class="[cardClass, 'border-red-100 dark:border-red-900/40']"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        Putuskan Nomor
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Token akses akan dihapus, bukan sekadar ditandai tidak
                        aktif. Riwayat pesan tetap tersimpan.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-red-200 px-4 py-2 text-xs font-bold text-red-700 transition-colors hover:bg-red-50 sm:text-sm dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40"
                    @click="disconnect"
                >
                    Putuskan WhatsApp
                </button>
            </div>
        </div>
    </div>
</template>
