<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { pay as payRoute } from '@/routes/billing';
import type {
    InvoiceRow,
    PlanFeature,
    PlanSummary,
    SubscriptionSummary,
    UsageSummary,
} from '@/types/billing';

/**
 * "Paket saya" (B-2, tugas 26 Nov).
 *
 * Everything on this page comes from the same services that enforce the rules
 * (`Entitlements`, `SeatQuota`, `MonthlyQuota`), so it cannot promise a feature or
 * a quota the server would refuse.
 *
 * Upgrade/downgrade is deliberately absent: plan changes are a gelombang-2
 * feature (G-2), so the page says the plan is managed by the operator instead of
 * offering a button that goes nowhere. Paying an outstanding invoice is not an
 * upgrade — it is the period the tenant already has.
 */
const props = defineProps<{
    plan: PlanSummary;
    addons: PlanFeature[];
    subscription: SubscriptionSummary | null;
    usage: UsageSummary;
    invoices: InvoiceRow[];
    paymentEnabled: boolean;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const payingInvoice = ref<string | null>(null);

const cardClass =
    'rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900';

const intervalLabel = computed(() => {
    switch (props.subscription?.interval) {
        case 'monthly':
            return 'Bulanan';
        case 'yearly':
            return 'Tahunan';
        default:
            return 'Belum ada pembayaran';
    }
});

const seatPercent = computed(() =>
    props.usage.users.limit > 0
        ? Math.min(
              100,
              (props.usage.users.used / props.usage.users.limit) * 100,
          )
        : 100,
);

const aiPercent = computed(() =>
    props.usage.ai.limit > 0
        ? Math.min(100, (props.usage.ai.used / props.usage.ai.limit) * 100)
        : 0,
);

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

function pay(invoice: InvoiceRow): void {
    payingInvoice.value = invoice.number;

    router.post(
        payRoute.url({
            current_team: teamSlug.value,
            invoice: invoice.id,
        }),
        {},
        {
            onFinish: () => (payingInvoice.value = null),
        },
    );
}
</script>

<template>
    <Head title="Paket saya" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-xl font-bold text-slate-900 sm:text-2xl dark:text-slate-100"
            >
                Paket saya
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Paket yang aktif, fitur yang menyertainya, kuota yang berlaku,
                dan tagihan yang belum lunas.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- Plan -->
            <div :class="[cardClass, 'lg:col-span-2']">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2
                                class="text-lg font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ plan.name }}
                            </h2>
                            <span
                                v-if="plan.code"
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold tracking-wide text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ plan.code }}
                            </span>
                        </div>
                        <p
                            v-if="plan.description"
                            class="mt-1 max-w-xl text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ plan.description }}
                        </p>
                    </div>

                    <span
                        v-if="subscription"
                        class="rounded-full px-3 py-1 text-xs font-bold"
                        :class="
                            subscription.status === 'active' ||
                            subscription.status === 'trialing'
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'
                        "
                    >
                        {{ subscription.statusLabel }}
                    </span>
                </div>

                <dl
                    class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                    >
                        <dt
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Siklus tagihan
                        </dt>
                        <dd
                            class="mt-1 font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ intervalLabel }}
                        </dd>
                    </div>
                    <div
                        class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                    >
                        <dt
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Berlaku sampai
                        </dt>
                        <dd
                            class="mt-1 font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{
                                subscription?.status === 'trialing'
                                    ? formatDate(subscription.trialEndsAt)
                                    : formatDate(
                                          subscription?.periodEnd ?? null,
                                      )
                            }}
                        </dd>
                    </div>
                    <div
                        class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                    >
                        <dt
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Harga bulanan
                        </dt>
                        <dd
                            class="mt-1 font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ formatRupiah(plan.priceMonthly) }}
                        </dd>
                    </div>
                    <div
                        class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                    >
                        <dt
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Harga tahunan
                        </dt>
                        <dd
                            class="mt-1 font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ formatRupiah(plan.priceYearly) }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <h3
                        class="text-sm font-bold text-slate-900 dark:text-slate-100"
                    >
                        Fitur yang aktif
                    </h3>
                    <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <li
                            v-for="feature in plan.features"
                            :key="feature.value"
                            class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300"
                        >
                            <span
                                class="material-symbols-outlined mt-0.5 text-[18px] text-emerald-600 dark:text-emerald-400"
                                >check_circle</span
                            >
                            <span>{{ feature.label }}</span>
                        </li>
                    </ul>

                    <div v-if="addons.length > 0" class="mt-4">
                        <h4
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        >
                            Add-on aktif
                        </h4>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="addon in addons"
                                :key="addon.value"
                                class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                            >
                                {{ addon.label }}
                            </span>
                        </div>
                    </div>
                </div>

                <p
                    class="mt-6 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300"
                >
                    Perubahan paket (upgrade/downgrade) belum bisa dilakukan
                    sendiri. Hubungi operator platform untuk mengubah paket atau
                    membeli add-on.
                </p>
            </div>

            <!-- Usage -->
            <div :class="cardClass">
                <h2
                    class="text-base font-bold text-slate-900 dark:text-slate-100"
                >
                    Kuota terpakai
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Angka yang sama dengan yang diperiksa server saat menambah
                    anggota atau memakai fitur.
                </p>

                <div class="mt-5 space-y-5">
                    <div>
                        <div
                            class="flex items-baseline justify-between text-sm"
                        >
                            <span
                                class="font-semibold text-slate-700 dark:text-slate-200"
                                >Pengguna</span
                            >
                            <span class="text-slate-500 dark:text-slate-400">
                                {{ usage.users.used }} / {{ usage.users.limit }}
                            </span>
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full rounded-full bg-blue-600 dark:bg-blue-500"
                                :style="{ width: `${seatPercent}%` }"
                            ></div>
                        </div>
                    </div>

                    <div>
                        <div
                            class="flex items-baseline justify-between text-sm"
                        >
                            <span
                                class="font-semibold text-slate-700 dark:text-slate-200"
                                >AI generate ({{ usage.ai.period }})</span
                            >
                            <span class="text-slate-500 dark:text-slate-400">
                                {{ usage.ai.used }} / {{ usage.ai.limit }}
                            </span>
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full rounded-full bg-indigo-600 dark:bg-indigo-500"
                                :style="{ width: `${aiPercent}%` }"
                            ></div>
                        </div>
                        <p
                            v-if="usage.ai.limit === 0"
                            class="mt-2 text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Content AI tidak termasuk paket ini.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices -->
        <div :class="cardClass">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2
                        class="text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        Tagihan
                    </h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Bayar langsung dari sini; pembayaran dibuka di halaman
                        Midtrans.
                    </p>
                </div>
                <span
                    v-if="!paymentEnabled"
                    class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                >
                    Pembayaran online belum aktif
                </span>
            </div>

            <p
                v-if="invoices.length === 0"
                class="mt-4 rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                Belum ada tagihan. Tagihan pertama terbit saat periode berbayar
                dimulai.
            </p>

            <div v-else class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400"
                        >
                            <th class="py-2 pr-3">Nomor</th>
                            <th class="py-2 pr-3">Periode</th>
                            <th class="py-2 pr-3">Jatuh tempo</th>
                            <th class="py-2 pr-3">Nominal</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            class="border-b border-slate-50 last:border-0 dark:border-slate-800/60"
                        >
                            <td
                                class="py-3 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300"
                            >
                                {{ invoice.number }}
                            </td>
                            <td
                                class="py-3 pr-3 text-slate-600 dark:text-slate-300"
                            >
                                {{ formatDate(invoice.periodStart) }} –
                                {{ formatDate(invoice.periodEnd) }}
                            </td>
                            <td
                                class="py-3 pr-3 text-slate-600 dark:text-slate-300"
                            >
                                {{ formatDate(invoice.dueAt) }}
                            </td>
                            <td
                                class="py-3 pr-3 font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ formatRupiah(invoice.amount) }}
                            </td>
                            <td class="py-3 pr-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                                    :class="
                                        invoice.status === 'paid'
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                            : invoice.status === 'open'
                                              ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'
                                              : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                >
                                    {{ invoice.statusLabel }}
                                </span>
                            </td>
                            <td class="py-3 text-right">
                                <button
                                    v-if="
                                        invoice.isOpen &&
                                        paymentEnabled &&
                                        subscription !== null
                                    "
                                    type="button"
                                    class="rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white transition-colors hover:bg-blue-700 disabled:opacity-60"
                                    :disabled="payingInvoice === invoice.number"
                                    @click="pay(invoice)"
                                >
                                    {{
                                        payingInvoice === invoice.number
                                            ? 'Membuka…'
                                            : 'Bayar'
                                    }}
                                </button>
                                <span
                                    v-else-if="invoice.paidAt"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Lunas {{ formatDate(invoice.paidAt) }}
                                </span>
                                <span v-else class="text-xs text-slate-400"
                                    >—</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
