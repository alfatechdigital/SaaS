<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import {
    destroy as transactionDestroy,
    store as transactionStore,
} from '@/routes/transactions';
import type {
    ProjectOption,
    Transaction,
    TransactionCategory,
    TransactionType,
} from '@/types';
import { formatRupiah, formatRupiahShort } from '@/utils/formatters';

/**
 * Cashflow ledger with per-project profitability.
 *
 * Ported from `FinancePage.tsx`. Differences from the template:
 * - The server derives `type` from `category` (`TransactionCategory::type()`),
 *   so the form only submits `category`. The income/expense switch is local
 *   state that swaps the category options.
 * - `created_by_id` is set server-side from the authenticated user, so the
 *   template's `createdBy` string is not sent.
 * - Category labels come from the Resource (`categoryLabel`) instead of
 *   upper-casing the raw enum value.
 * - The modal subtitle no longer hardcodes the company name.
 *
 * The template only supports create + delete here (no edit), and that is kept.
 */
const props = defineProps<{
    transactions: Transaction[];
    projects: ProjectOption[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const filterType = ref<'all' | TransactionType>('all');
const isModalOpen = ref(false);
const transactionType = ref<TransactionType>('income');

const incomeCategories: TransactionCategory[] = [
    'project_income',
    'capital',
    'other',
];
const expenseCategories: TransactionCategory[] = [
    'company_expense',
    'project_expense',
    'other',
];

const categoryLabels: Record<TransactionCategory, string> = {
    project_income: 'Pembayaran Proyek Klien',
    project_expense: 'Biaya Langsung Proyek (Hardware/API)',
    company_expense: 'Biaya Operasional Kantor / Gaji',
    capital: 'Modal Tambahan / Setoran',
    other: 'Lain-lain',
};

const inputClass =
    'w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none';
const labelClass =
    'block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1';

const totals = computed(() => {
    const income = props.transactions
        .filter((transaction) => transaction.type === 'income')
        .reduce((total, transaction) => total + transaction.amount, 0);

    const expense = props.transactions
        .filter((transaction) => transaction.type === 'expense')
        .reduce((total, transaction) => total + transaction.amount, 0);

    const netProfit = income - expense;

    return {
        income,
        expense,
        netProfit,
        margin: income > 0 ? ((netProfit / income) * 100).toFixed(1) : '0.0',
    };
});

const projectProfitability = computed(() =>
    props.projects.map((project) => {
        const income = props.transactions
            .filter(
                (transaction) =>
                    transaction.type === 'income' &&
                    transaction.projectId === project.id,
            )
            .reduce((total, transaction) => total + transaction.amount, 0);

        const expense = props.transactions
            .filter(
                (transaction) =>
                    transaction.type === 'expense' &&
                    transaction.projectId === project.id,
            )
            .reduce((total, transaction) => total + transaction.amount, 0);

        const profit = income - expense;

        return {
            project,
            income,
            expense,
            profit,
            margin: income > 0 ? ((profit / income) * 100).toFixed(0) : '0',
        };
    }),
);

const filteredTransactions = computed(() =>
    props.transactions.filter(
        (transaction) =>
            filterType.value === 'all' || transaction.type === filterType.value,
    ),
);

const form = useForm({
    category: 'project_income' as TransactionCategory,
    project_id: null as number | null,
    description: '',
    amount: 15_000_000,
    date: new Date().toISOString().split('T')[0],
});

const availableCategories = computed(() =>
    transactionType.value === 'income' ? incomeCategories : expenseCategories,
);

function openCreateModal(type: TransactionType): void {
    transactionType.value = type;

    form.clearErrors();
    form.category = type === 'income' ? 'project_income' : 'company_expense';
    form.project_id = null;
    form.description = '';
    form.amount = type === 'income' ? 15_000_000 : 10_000_000;
    form.date = new Date().toISOString().split('T')[0];

    isModalOpen.value = true;
}

function changeTransactionType(type: TransactionType): void {
    transactionType.value = type;
    form.category = type === 'income' ? 'project_income' : 'company_expense';
}

function saveTransaction(): void {
    form.post(transactionStore.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        onSuccess: () => {
            isModalOpen.value = false;
        },
    });
}

function removeTransaction(transaction: Transaction): void {
    if (
        !window.confirm(`Hapus catatan transaksi "${transaction.description}"?`)
    ) {
        return;
    }

    router.delete(
        transactionDestroy.url({
            current_team: teamSlug.value,
            transaction: transaction.id,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Keuangan" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                >
                    Keuangan &amp; Arus Kas Operasional
                </h1>
                <p
                    class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    Monitoring cashflow, profitabilitas per proyek, dan
                    pengeluaran beban operasional.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    class="flex items-center gap-1 rounded-xl bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-700 transition-all hover:bg-rose-100 sm:text-sm dark:bg-rose-950/50 dark:text-rose-300"
                    @click="openCreateModal('expense')"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >remove_circle</span
                    >
                    <span>+ Catat Pengeluaran</span>
                </button>
                <button
                    class="flex items-center gap-1 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-emerald-700 sm:text-sm"
                    @click="openCreateModal('income')"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >add_circle</span
                    >
                    <span>+ Catat Pemasukan</span>
                </button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    <span>Total Pemasukan (Revenue)</span>
                    <span
                        class="material-symbols-outlined text-[20px] text-emerald-600"
                        >arrow_downward</span
                    >
                </div>
                <div
                    class="mt-2 text-2xl font-black text-slate-900 dark:text-slate-100"
                >
                    {{ formatRupiah(totals.income) }}
                </div>
                <div
                    class="mt-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-400"
                >
                    Termin proyek &amp; pembayaran klien
                </div>
            </div>

            <div
                class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    <span>Total Beban Pengeluaran</span>
                    <span
                        class="material-symbols-outlined text-[20px] text-rose-600"
                        >arrow_upward</span
                    >
                </div>
                <div
                    class="mt-2 text-2xl font-black text-slate-900 dark:text-slate-100"
                >
                    {{ formatRupiah(totals.expense) }}
                </div>
                <div
                    class="mt-1 text-[11px] font-bold text-rose-600 dark:text-rose-400"
                >
                    Gaji tim, cloud server &amp; operasional
                </div>
            </div>

            <div
                class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    <span>Estimasi Laba Bersih (Net Profit)</span>
                    <span
                        class="material-symbols-outlined text-[20px] text-blue-600"
                        >account_balance</span
                    >
                </div>
                <div
                    :class="[
                        'mt-2 text-2xl font-black',
                        totals.netProfit < 0
                            ? 'text-rose-700 dark:text-rose-400'
                            : 'text-emerald-700 dark:text-emerald-400',
                    ]"
                >
                    {{ formatRupiah(totals.netProfit) }}
                </div>
                <div
                    class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400"
                >
                    Margin Operasional:
                    <span class="font-bold text-slate-800 dark:text-slate-200"
                        >{{ totals.margin }}%</span
                    >
                </div>
            </div>
        </div>

        <!-- Project profitability -->
        <div
            class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h2
                    class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Profitabilitas per Proyek Software
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Analisis efisiensi laba langsung: pemasukan proyek dikurangi
                    biaya pengeluaran langsung proyek.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-slate-100 bg-slate-50 font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-4 py-3">Nama Proyek</th>
                            <th class="px-3 py-3">Klien</th>
                            <th class="px-3 py-3">Nilai Kontrak</th>
                            <th class="px-3 py-3">Uang Masuk</th>
                            <th class="px-3 py-3">Biaya Langsung</th>
                            <th class="px-3 py-3">Laba Proyek</th>
                            <th class="px-3 py-3">Margin</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr v-if="projectProfitability.length === 0">
                            <td
                                colspan="7"
                                class="py-8 text-center text-slate-400"
                            >
                                Belum ada proyek tercatat.
                            </td>
                        </tr>

                        <tr
                            v-for="row in projectProfitability"
                            :key="row.project.id"
                            class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/50"
                        >
                            <td
                                class="px-4 py-3 font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ row.project.name }}
                            </td>
                            <td
                                class="px-3 py-3 text-slate-600 dark:text-slate-300"
                            >
                                {{ row.project.clientName ?? '-' }}
                            </td>
                            <td
                                class="px-3 py-3 font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{
                                    formatRupiahShort(row.project.projectValue)
                                }}
                            </td>
                            <td
                                class="px-3 py-3 font-bold text-emerald-700 dark:text-emerald-400"
                            >
                                {{
                                    row.income > 0
                                        ? formatRupiahShort(row.income)
                                        : '-'
                                }}
                            </td>
                            <td
                                class="px-3 py-3 font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{
                                    row.expense > 0
                                        ? formatRupiahShort(row.expense)
                                        : 'Rp 0'
                                }}
                            </td>
                            <td
                                :class="[
                                    'px-3 py-3 font-black',
                                    row.profit < 0
                                        ? 'text-rose-700 dark:text-rose-400'
                                        : 'text-slate-900 dark:text-slate-100',
                                ]"
                            >
                                {{
                                    row.income > 0
                                        ? formatRupiahShort(row.profit)
                                        : '-'
                                }}
                            </td>
                            <td
                                class="px-3 py-3 font-bold text-blue-700 dark:text-blue-400"
                            >
                                {{ row.income > 0 ? `${row.margin}%` : '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ledger -->
        <div
            class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
            >
                <div>
                    <h2
                        class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Buku Kas &amp; Riwayat Transaksi
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Log pencatatan mutasi kas perusahaan.
                    </p>
                </div>

                <div
                    class="flex items-center gap-1 self-start rounded-xl bg-slate-100 p-1 text-xs font-semibold sm:self-auto dark:bg-slate-800"
                >
                    <button
                        :class="[
                            'rounded-lg px-3 py-1.5 transition-all',
                            filterType === 'all'
                                ? 'bg-white font-bold text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-100'
                                : 'text-slate-500 dark:text-slate-400',
                        ]"
                        @click="filterType = 'all'"
                    >
                        Semua Mutasi
                    </button>
                    <button
                        :class="[
                            'rounded-lg px-3 py-1.5 transition-all',
                            filterType === 'income'
                                ? 'bg-emerald-600 font-bold text-white shadow-xs'
                                : 'text-slate-500 dark:text-slate-400',
                        ]"
                        @click="filterType = 'income'"
                    >
                        Pemasukan
                    </button>
                    <button
                        :class="[
                            'rounded-lg px-3 py-1.5 transition-all',
                            filterType === 'expense'
                                ? 'bg-rose-600 font-bold text-white shadow-xs'
                                : 'text-slate-500 dark:text-slate-400',
                        ]"
                        @click="filterType = 'expense'"
                    >
                        Pengeluaran
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-slate-100 bg-slate-50 font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-3 py-3">Tipe</th>
                            <th class="px-3 py-3">Kategori</th>
                            <th class="px-3 py-3">Keterangan</th>
                            <th class="px-3 py-3">Proyek Terkait</th>
                            <th class="px-3 py-3">Pencatat</th>
                            <th class="px-3 py-3">Nominal (Rp)</th>
                            <th class="px-3 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr v-if="filteredTransactions.length === 0">
                            <td
                                colspan="8"
                                class="py-8 text-center text-slate-400"
                            >
                                Belum ada mutasi kas tercatat.
                            </td>
                        </tr>

                        <tr
                            v-for="transaction in filteredTransactions"
                            :key="transaction.id"
                            class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                        >
                            <td
                                class="px-4 py-3 font-medium whitespace-nowrap text-slate-600 dark:text-slate-300"
                            >
                                {{ transaction.date }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold',
                                        transaction.type === 'income'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                            : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
                                    ]"
                                >
                                    {{ transaction.typeLabel }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-3 font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ transaction.categoryLabel }}
                            </td>
                            <td
                                class="max-w-xs px-3 py-3 font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ transaction.description }}
                            </td>
                            <td
                                class="px-3 py-3 text-slate-500 dark:text-slate-400"
                            >
                                {{ transaction.projectName ?? '-' }}
                            </td>
                            <td
                                class="px-3 py-3 text-slate-500 dark:text-slate-400"
                            >
                                {{ transaction.createdByName ?? '-' }}
                            </td>
                            <td
                                :class="[
                                    'px-3 py-3 font-bold whitespace-nowrap',
                                    transaction.type === 'income'
                                        ? 'text-emerald-700 dark:text-emerald-400'
                                        : 'text-rose-600 dark:text-rose-400',
                                ]"
                            >
                                {{ transaction.type === 'income' ? '+' : '-' }}
                                {{ formatRupiah(transaction.amount) }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <button
                                    class="rounded p-1 text-slate-400 hover:text-rose-600"
                                    @click="removeTransaction(transaction)"
                                >
                                    <span
                                        class="material-symbols-outlined text-[16px]"
                                        >delete</span
                                    >
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: record transaction -->
        <Modal
            :is-open="isModalOpen"
            :title="
                transactionType === 'income'
                    ? 'Catat Pemasukan Kas'
                    : 'Catat Pengeluaran Operasional'
            "
            subtitle="Masukkan detail transaksi keuangan ke dalam buku kas."
            @close="isModalOpen = false"
        >
            <form
                class="space-y-4 text-xs sm:text-sm"
                @submit.prevent="saveTransaction"
            >
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass">Tipe Transaksi</label>
                        <select
                            :value="transactionType"
                            :class="[inputClass, 'cursor-pointer']"
                            @change="
                                changeTransactionType(
                                    ($event.target as HTMLSelectElement)
                                        .value as TransactionType,
                                )
                            "
                        >
                            <option value="income">
                                Pemasukan Kas (Income)
                            </option>
                            <option value="expense">
                                Pengeluaran Kas (Expense)
                            </option>
                        </select>
                    </div>

                    <div>
                        <label :class="labelClass">Kategori</label>
                        <select
                            v-model="form.category"
                            :class="[inputClass, 'cursor-pointer']"
                        >
                            <option
                                v-for="category in availableCategories"
                                :key="category"
                                :value="category"
                            >
                                {{ categoryLabels[category] }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.category"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.category }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass">Nominal (Rp) *</label>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            min="1"
                            required
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.amount"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.amount }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Tanggal Transaksi</label>
                        <input
                            v-model="form.date"
                            type="date"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.date"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.date }}
                        </p>
                    </div>
                </div>

                <div>
                    <label :class="labelClass">Terkait Proyek (Opsional)</label>
                    <select
                        v-model="form.project_id"
                        :class="[inputClass, 'cursor-pointer']"
                    >
                        <option :value="null">
                            -- Tidak Terikat Proyek Spesifik --
                        </option>
                        <option
                            v-for="project in projects"
                            :key="project.id"
                            :value="project.id"
                        >
                            {{ project.name
                            }}{{
                                project.clientName
                                    ? ` (${project.clientName})`
                                    : ''
                            }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.project_id"
                        class="mt-1 text-[11px] text-rose-600"
                    >
                        {{ form.errors.project_id }}
                    </p>
                </div>

                <div>
                    <label :class="labelClass">Keterangan Transaksi *</label>
                    <input
                        v-model="form.description"
                        required
                        placeholder="Contoh: Pembayaran DP 50% Termin 1 via Transfer BCA"
                        :class="inputClass"
                    />
                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-[11px] text-rose-600"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-end gap-3 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="isModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-[#1e40af] px-5 py-2 text-xs font-bold text-white shadow-sm transition-all hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Menyimpan...'
                                : 'Simpan Transaksi'
                        }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
