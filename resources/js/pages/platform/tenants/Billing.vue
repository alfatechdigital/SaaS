<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { destroy, store } from '@/routes/platform/tenants/addons';
import { index as tenantsIndex } from '@/routes/platform/tenants';
import { index as plansIndex } from '@/routes/platform/plans';
import type {
    AddonRow,
    InvoiceRow,
    PlanFeature,
    SubscriptionSummary,
    UsageSummary,
} from '@/types/billing';

/**
 * One tenant's billing, seen by the platform operator (F-8).
 *
 * The panel can do exactly one entitlement change: grant or revoke an add-on
 * (PDR-06). The plan itself follows the subscription, and upgrade/downgrade is a
 * gelombang-2 feature (G-2) — so no plan dropdown is offered here.
 *
 * The tenant identifier in the URL is the slug, because that is what the platform
 * routes bind (same as the tenant list).
 */
const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        suspended: boolean;
    };
    plan: {
        code: string;
        name: string;
        priceMonthly: number | null;
        priceYearly: number | null;
        features: PlanFeature[];
        limits: Record<string, number>;
    };
    entitlement: {
        features: PlanFeature[];
        addonFeatures: PlanFeature[];
    };
    subscription: SubscriptionSummary | null;
    addons: AddonRow[];
    grantableFeatures: PlanFeature[];
    usage: UsageSummary;
    invoices: Array<Omit<InvoiceRow, 'id' | 'hasPaymentLink'>>;
}>();

const grantForm = useForm({ feature: '' });

const cardClass =
    'rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900';

const limitLabels: Record<string, string> = {
    users: 'Pengguna',
    ai_generations_per_month: 'AI generate / bulan',
    whatsapp_messages_per_month: 'Pesan WhatsApp / bulan',
};

function grant(): void {
    grantForm.post(store.url({ team: props.tenant.slug }), {
        preserveScroll: true,
        onSuccess: () => grantForm.reset('feature'),
    });
}

function revoke(addon: AddonRow): void {
    const confirmed = window.confirm(
        `Cabut add-on ${addon.label}? Fitur ini langsung tidak tersedia untuk tenant.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        destroy.url({ team: props.tenant.slug, feature: addon.feature }),
        { preserveScroll: true },
    );
}

function formatRupiah(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}
</script>

<template>
    <Head :title="`Billing ${tenant.name}`" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div
                    class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
                >
                    <Link :href="tenantsIndex()" class="hover:underline"
                        >Tenant</Link
                    >
                    <span>/</span>
                    <span>{{ tenant.name }}</span>
                </div>
                <h1
                    class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100"
                >
                    Paket tenant: {{ tenant.name }}
                </h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    Langganan berjalan, fitur efektif, add-on, dan kuota yang
                    ditegakkan server.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span
                    v-if="tenant.suspended"
                    class="rounded-full bg-rose-50 px-3 py-1 text-[11px] font-bold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
                >
                    Tenant disuspend
                </span>
                <Link
                    :href="plansIndex()"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:border-blue-200 hover:text-blue-700 dark:border-slate-700 dark:text-slate-200"
                >
                    Kelola paket
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- Plan & subscription -->
            <div :class="[cardClass, 'lg:col-span-2']">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2
                            class="text-lg font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ plan.name }}
                            <span
                                class="font-mono text-xs text-slate-500 dark:text-slate-400"
                            >
                                ({{ plan.code }})
                            </span>
                        </h2>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Harga berjalan:
                            {{ formatRupiah(plan.priceMonthly) }} / bulan ·
                            {{ formatRupiah(plan.priceYearly) }} / tahun
                        </p>
                    </div>

                    <span
                        v-if="subscription"
                        class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        {{ subscription.statusLabel }}
                    </span>
                    <span
                        v-else
                        class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                    >
                        Tanpa langganan (paket default)
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                    <div
                        class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                    >
                        <dt
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Mulai periode
                        </dt>
                        <dd
                            class="mt-1 font-semibold text-slate-800 dark:text-slate-200"
                        >
                            {{ formatDate(subscription?.periodStart ?? null) }}
                        </dd>
                    </div>
                    <div
                        class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                    >
                        <dt
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Akhir periode
                        </dt>
                        <dd
                            class="mt-1 font-semibold text-slate-800 dark:text-slate-200"
                        >
                            {{ formatDate(subscription?.periodEnd ?? null) }}
                        </dd>
                    </div>
                    <div
                        class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                    >
                        <dt
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Berakhir trial
                        </dt>
                        <dd
                            class="mt-1 font-semibold text-slate-800 dark:text-slate-200"
                        >
                            {{ formatDate(subscription?.trialEndsAt ?? null) }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5">
                    <h3
                        class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Fitur efektif ({{ entitlement.features.length }})
                    </h3>
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        <li
                            v-for="feature in entitlement.features"
                            :key="feature.value"
                            class="rounded-full px-2 py-0.5 text-[11px]"
                            :class="
                                entitlement.addonFeatures.some(
                                    (addon) => addon.value === feature.value,
                                )
                                    ? 'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                            "
                        >
                            {{ feature.label }}
                        </li>
                    </ul>
                    <p
                        class="mt-2 text-[11px] text-slate-500 dark:text-slate-400"
                    >
                        Biru = berasal dari add-on. Fitur paket tetap mengikuti
                        <code>config/plans.php</code>.
                    </p>
                </div>

                <div class="mt-5">
                    <h3
                        class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Kuota paket
                    </h3>
                    <ul
                        class="mt-2 grid grid-cols-1 gap-1 text-sm text-slate-700 sm:grid-cols-2 dark:text-slate-300"
                    >
                        <li
                            v-for="(limit, metric) in plan.limits"
                            :key="metric"
                            class="flex justify-between gap-3 rounded-lg bg-slate-50 px-2 py-1 dark:bg-slate-800/60"
                        >
                            <span>{{ limitLabels[metric] ?? metric }}</span>
                            <span class="font-bold">{{ limit }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Usage -->
            <div :class="cardClass">
                <h2
                    class="text-base font-bold text-slate-900 dark:text-slate-100"
                >
                    Pemakaian saat ini
                </h2>
                <div class="mt-4 space-y-4 text-sm">
                    <div class="flex items-baseline justify-between">
                        <span class="text-slate-600 dark:text-slate-300"
                            >Pengguna</span
                        >
                        <span
                            class="font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ usage.users.used }} / {{ usage.users.limit }}
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <span class="text-slate-600 dark:text-slate-300">
                            AI generate ({{ usage.ai.period }})
                        </span>
                        <span
                            class="font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ usage.ai.used }} / {{ usage.ai.limit }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add-ons -->
        <div :class="cardClass">
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Add-on fitur
            </h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Add-on membeli satu fitur di luar paket (PDR-06); label paket
                tenant tidak berubah.
            </p>

            <form
                class="mt-4 flex flex-wrap items-end gap-3"
                @submit.prevent="grant"
            >
                <div class="min-w-56 flex-1">
                    <label
                        for="grant-feature"
                        class="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300"
                    >
                        Fitur yang bisa ditambahkan
                    </label>
                    <select
                        id="grant-feature"
                        v-model="grantForm.feature"
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="" disabled>Pilih fitur…</option>
                        <option
                            v-for="feature in grantableFeatures"
                            :key="feature.value"
                            :value="feature.value"
                        >
                            {{ feature.label }}
                        </option>
                    </select>
                    <InputError
                        :message="grantForm.errors.feature"
                        class="mt-1"
                    />
                </div>
                <button
                    type="submit"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-blue-700 disabled:opacity-60"
                    :disabled="
                        grantForm.processing || grantableFeatures.length === 0
                    "
                >
                    {{ grantForm.processing ? 'Menyimpan…' : 'Beri add-on' }}
                </button>
            </form>

            <p
                v-if="grantableFeatures.length === 0"
                class="mt-2 text-xs text-slate-500 dark:text-slate-400"
            >
                Semua fitur sudah tersedia lewat paket atau add-on aktif.
            </p>

            <p
                v-if="addons.length === 0"
                class="mt-4 rounded-xl border border-dashed border-slate-200 p-3 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                Tenant ini belum pernah membeli add-on.
            </p>

            <ul
                v-else
                class="mt-4 divide-y divide-slate-100 dark:divide-slate-800"
            >
                <li
                    v-for="addon in addons"
                    :key="addon.feature"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <p
                            class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                        >
                            {{ addon.label }}
                        </p>
                        <p
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            {{ formatDate(addon.startsAt) }} →
                            {{
                                addon.endsAt
                                    ? formatDate(addon.endsAt)
                                    : 'tanpa batas'
                            }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                            :class="
                                addon.active
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                            "
                        >
                            {{ addon.active ? 'Aktif' : 'Berakhir' }}
                        </span>
                        <button
                            v-if="addon.active"
                            type="button"
                            class="rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            @click="revoke(addon)"
                        >
                            Cabut
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Invoices (read-only: paying is the tenant's action) -->
        <div :class="cardClass">
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Tagihan terakhir
            </h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Hanya-baca: pembayaran dilakukan tenant dari halaman "Paket
                saya".
            </p>

            <p
                v-if="invoices.length === 0"
                class="mt-3 text-sm text-slate-500 dark:text-slate-400"
            >
                Belum ada tagihan.
            </p>

            <div v-else class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400"
                        >
                            <th class="py-2 pr-3">Nomor</th>
                            <th class="py-2 pr-3">Jatuh tempo</th>
                            <th class="py-2 pr-3">Nominal</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="invoice in invoices"
                            :key="invoice.number"
                            class="border-b border-slate-50 last:border-0 dark:border-slate-800/60"
                        >
                            <td
                                class="py-2 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300"
                            >
                                {{ invoice.number }}
                            </td>
                            <td
                                class="py-2 pr-3 text-slate-600 dark:text-slate-300"
                            >
                                {{ formatDate(invoice.dueAt) }}
                            </td>
                            <td
                                class="py-2 pr-3 font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ formatRupiah(invoice.amount) }}
                            </td>
                            <td class="py-2 text-slate-600 dark:text-slate-300">
                                {{ invoice.statusLabel }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
