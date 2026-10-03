<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { index as contentsIndex } from '@/routes/contents';
import { index as leadsIndex } from '@/routes/leads';
import { index as projectsIndex } from '@/routes/projects';
import type {
    ActivityLog,
    ContentItem,
    DashboardInvitation,
    Lead,
    Project,
    Transaction,
} from '@/types';
import {
    formatDateTimeId,
    formatRupiah,
    formatRupiahShort,
    formatTimeAgo,
    getContentStatusBadge,
    getProjectStatusBadge,
} from '@/utils/formatters';

/**
 * Operational dashboard.
 *
 * Ported from `DashboardPage.tsx`. This page lives under `admin/` on purpose:
 * `app.ts` picks the layout from the component name, so the old root-level
 * `Dashboard.vue` rendered inside the starter kit shell — "Laravel" branding and
 * no module navigation — while every other page showed the Alfatech shell
 * (see docs/IMPLEMENTATION_PLAN.md TBD-07).
 *
 * Fake data from the template was replaced with real derivations:
 * - The month selector listed hardcoded months (`Oktober 2024`, …). It is now
 *   built from the months actually present in the ledger.
 * - "+18.2% vs bln lalu" and "Target Q4: 82% tercapai" were hardcoded. The
 *   month-over-month figure is now computed, and the target line is gone.
 * - "Kapasitas Tim 85% Terutilisasi" was hardcoded; the card now shows the
 *   average progress of active projects.
 * - `winRate` fell back to a fake `68`; it is now `null` (rendered as `-`) when
 *   nothing has been closed.
 * - "Unduh Laporan" really downloads the selected month's ledger as CSV.
 * - Relative timestamps only render after mount so SSR output matches the first
 *   client render (ADR-19).
 */
const props = defineProps<{
    projects: Project[];
    leads: Lead[];
    contents: ContentItem[];
    transactions: Transaction[];
    activityLogs: ActivityLog[];
    pendingInvitations?: DashboardInvitation[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const currentUser = computed(() => page.props.auth.user);

const MONTH_NAMES = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const mounted = ref(false);
const selectedMonth = ref('');

onMounted(() => {
    mounted.value = true;
});

/** `YYYY-MM` keys present in the ledger, newest first. */
const monthKeys = computed(() => {
    const keys = new Set<string>();

    props.transactions.forEach((transaction) => {
        if (transaction.date) {
            keys.add(transaction.date.slice(0, 7));
        }
    });

    return Array.from(keys).sort((a, b) => b.localeCompare(a));
});

const activeMonth = computed(
    () => selectedMonth.value || monthKeys.value[0] || '',
);

function formatMonthLabel(key: string): string {
    const [year, month] = key.split('-');

    return `${MONTH_NAMES[Number(month) - 1] ?? month} ${year}`;
}

const monthOptions = computed(() =>
    monthKeys.value.map((key) => ({
        value: key,
        label: formatMonthLabel(key),
    })),
);

function sumByType(
    transactions: Transaction[],
    type: 'income' | 'expense',
): number {
    return transactions
        .filter((transaction) => transaction.type === type)
        .reduce((total, transaction) => total + transaction.amount, 0);
}

const totalRevenue = computed(() => sumByType(props.transactions, 'income'));
const totalExpense = computed(() => sumByType(props.transactions, 'expense'));
const netProfit = computed(() => totalRevenue.value - totalExpense.value);
const profitMargin = computed(() =>
    totalRevenue.value > 0
        ? ((netProfit.value / totalRevenue.value) * 100).toFixed(1)
        : '0.0',
);

const monthTransactions = computed(() =>
    props.transactions.filter((transaction) =>
        transaction.date?.startsWith(activeMonth.value),
    ),
);

const monthRevenue = computed(() =>
    sumByType(monthTransactions.value, 'income'),
);

/** Calendar month before the selected one, if the ledger has it. */
const previousMonthRevenue = computed(() => {
    const [year, month] = activeMonth.value.split('-').map(Number);

    if (!year || !month) {
        return null;
    }

    const date = new Date(year, month - 2, 1);
    const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

    if (!monthKeys.value.includes(key)) {
        return null;
    }

    return sumByType(
        props.transactions.filter((transaction) =>
            transaction.date?.startsWith(key),
        ),
        'income',
    );
});

const monthOverMonth = computed(() => {
    const previous = previousMonthRevenue.value;

    if (previous === null || previous <= 0) {
        return null;
    }

    return ((monthRevenue.value - previous) / previous) * 100;
});

const activeProjects = computed(() =>
    props.projects.filter(
        (project) =>
            project.status === 'development' ||
            project.status === 'review' ||
            project.status === 'deal',
    ),
);

const devProjectsCount = computed(
    () =>
        activeProjects.value.filter(
            (project) => project.status === 'development',
        ).length,
);

const reviewProjectsCount = computed(
    () =>
        activeProjects.value.filter((project) => project.status === 'review')
            .length,
);

const averageProgress = computed(() => {
    if (activeProjects.value.length === 0) {
        return 0;
    }

    const total = activeProjects.value.reduce(
        (sum, project) => sum + project.progress,
        0,
    );

    return Math.round(total / activeProjects.value.length);
});

const urgentProjects = computed(() =>
    [...activeProjects.value]
        .sort((a, b) =>
            (a.deadline ?? '9999-12-31').localeCompare(
                b.deadline ?? '9999-12-31',
            ),
        )
        .slice(0, 5),
);

const openPipelineValue = computed(() =>
    props.leads
        .filter((lead) => lead.status !== 'won' && lead.status !== 'lost')
        .reduce((total, lead) => total + lead.estimatedValue, 0),
);

const closedLeads = computed(() =>
    props.leads.filter(
        (lead) => lead.status === 'won' || lead.status === 'lost',
    ),
);

const winRate = computed(() => {
    if (closedLeads.value.length === 0) {
        return null;
    }

    const won = closedLeads.value.filter(
        (lead) => lead.status === 'won',
    ).length;

    return Math.round((won / closedLeads.value.length) * 100);
});

const funnel = computed(() => [
    {
        label: 'Kontak Baru',
        hint: 'Inbound Leads',
        value: props.leads.filter(
            (lead) => lead.status === 'new' || lead.status === 'contacted',
        ).length,
        class: 'bg-blue-50/60 border-blue-100 text-blue-700 dark:bg-blue-950/40 dark:border-blue-900/50 dark:text-blue-300',
    },
    {
        label: 'Follow Up & Meeting',
        hint: 'Presentasi Solusi',
        value: props.leads.filter(
            (lead) => lead.status === 'follow_up' || lead.status === 'meeting',
        ).length,
        class: 'bg-amber-50/60 border-amber-100 text-amber-700 dark:bg-amber-950/40 dark:border-amber-900/50 dark:text-amber-300',
    },
    {
        label: 'Proposal & Negosiasi',
        hint: 'Tahap Closing',
        value: props.leads.filter(
            (lead) =>
                lead.status === 'proposal' || lead.status === 'negotiation',
        ).length,
        class: 'bg-indigo-50/60 border-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:border-indigo-900/50 dark:text-indigo-300',
    },
    {
        label: 'Menang (Won)',
        hint: 'Deal Proyek Baru',
        value: props.leads.filter((lead) => lead.status === 'won').length,
        class: 'bg-emerald-50/60 border-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-900/50 dark:text-emerald-300',
    },
]);

const scheduledContents = computed(() => props.contents.slice(0, 3));

function progressBarClass(progress: number): string {
    if (progress > 80) {
        return 'bg-emerald-500';
    }

    if (progress > 50) {
        return 'bg-blue-600';
    }

    return 'bg-amber-500';
}

function logIcon(entityType: string): string {
    return (
        {
            transaction: 'payments',
            project: 'folder',
            lead: 'person',
            content: 'share',
            portfolio: 'work',
            company_profile: 'apartment',
            task: 'checklist',
        }[entityType] ?? 'notifications'
    );
}

function exportReport(): void {
    const header = [
        'Tanggal',
        'Tipe',
        'Kategori',
        'Keterangan',
        'Proyek',
        'Nominal',
    ];
    const rows = monthTransactions.value.map((transaction) => [
        transaction.date,
        transaction.typeLabel,
        transaction.categoryLabel,
        transaction.description,
        transaction.projectName ?? '',
        String(transaction.amount),
    ]);

    const summary = [
        ['', '', '', '', '', ''],
        ['Ringkasan', formatMonthLabel(activeMonth.value), '', '', '', ''],
        ['Pemasukan', '', '', '', '', String(monthRevenue.value)],
        [
            'Pengeluaran',
            '',
            '',
            '',
            '',
            String(sumByType(monthTransactions.value, 'expense')),
        ],
    ];

    const csv = [header, ...rows, ...summary]
        .map((row) =>
            row.map((cell) => `"${cell.replace(/"/g, '""')}"`).join(','),
        )
        .join('\r\n');

    // The BOM keeps Excel from mangling the Indonesian characters.
    const blob = new Blob([`\uFEFF${csv}`], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `laporan-${activeMonth.value || 'semua'}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="space-y-6 pb-12">
        <!-- Greeting & controls -->
        <div
            class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs transition-colors sm:p-6 md:flex-row md:items-center dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <h1
                        class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                    >
                        Selamat Datang, {{ currentUser?.name }}
                    </h1>
                    <span
                        class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300"
                    >
                        <span
                            class="mr-1.5 h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                        ></span>
                        Sistem Live
                    </span>
                </div>
                <p
                    class="max-w-2xl text-xs leading-relaxed text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    Ringkasan performa operasional, pipeline penjualan, dan
                    status proyek
                    {{ page.props.currentTeam?.name ?? 'tim ini' }}.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <select
                    v-if="monthOptions.length > 0"
                    v-model="selectedMonth"
                    :class="[
                        'cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
                    ]"
                >
                    <option
                        v-for="month in monthOptions"
                        :key="month.value"
                        :value="month.value"
                    >
                        {{ month.label }}
                    </option>
                </select>

                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    @click="exportReport"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >download</span
                    >
                    <span class="hidden sm:inline">Unduh Laporan</span>
                </button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                    >
                        Pendapatan
                        {{
                            activeMonth
                                ? formatMonthLabel(activeMonth)
                                : 'Total'
                        }}
                    </span>
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                    >
                        <span class="material-symbols-outlined text-[20px]"
                            >payments</span
                        >
                    </div>
                </div>
                <div class="mt-3">
                    <div
                        class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                    >
                        {{ formatRupiah(monthRevenue) }}
                    </div>
                    <div
                        v-if="monthOverMonth !== null"
                        :class="[
                            'mt-1 flex items-center gap-1 text-xs font-bold',
                            monthOverMonth >= 0
                                ? 'text-emerald-700 dark:text-emerald-400'
                                : 'text-rose-600 dark:text-rose-400',
                        ]"
                    >
                        <span class="material-symbols-outlined text-[16px]">
                            {{
                                monthOverMonth >= 0
                                    ? 'trending_up'
                                    : 'trending_down'
                            }}
                        </span>
                        <span
                            >{{ monthOverMonth >= 0 ? '+' : ''
                            }}{{ monthOverMonth.toFixed(1) }}%</span
                        >
                        <span class="font-normal text-slate-400"
                            >vs bulan sebelumnya</span
                        >
                    </div>
                    <div v-else class="mt-1 text-xs text-slate-400">
                        Belum ada pembanding bulan sebelumnya
                    </div>
                </div>
                <div
                    class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400"
                >
                    <span>Total sepanjang waktu</span>
                    <span
                        class="font-semibold text-slate-700 dark:text-slate-300"
                    >
                        {{ formatRupiahShort(totalRevenue) }}
                    </span>
                </div>
            </div>

            <div
                class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >Proyek Aktif</span
                    >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                    >
                        <span class="material-symbols-outlined text-[20px]"
                            >rocket_launch</span
                        >
                    </div>
                </div>
                <div class="mt-3">
                    <div
                        class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                    >
                        {{ activeProjects.length }} Proyek
                    </div>
                    <div
                        class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        <span
                            class="font-bold text-indigo-600 dark:text-indigo-400"
                            >{{ devProjectsCount }} Dev</span
                        >
                        &bull; {{ reviewProjectsCount }} UAT / Review
                    </div>
                </div>
                <div
                    class="mt-3 border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400"
                >
                    <div class="mb-1 flex justify-between">
                        <span>Rata-rata Progress</span>
                        <span
                            class="font-semibold text-slate-700 dark:text-slate-300"
                            >{{ averageProgress }}%</span
                        >
                    </div>
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-indigo-600 dark:bg-indigo-500"
                            :style="{ width: `${averageProgress}%` }"
                        ></div>
                    </div>
                </div>
            </div>

            <div
                class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >Pipeline Leads &amp; CRM</span
                    >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                    >
                        <span class="material-symbols-outlined text-[20px]"
                            >filter_alt</span
                        >
                    </div>
                </div>
                <div class="mt-3">
                    <div
                        class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                    >
                        {{ leads.length }} Prospek
                    </div>
                    <div
                        class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Potensi:
                        <span
                            class="font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ formatRupiahShort(openPipelineValue) }}
                        </span>
                    </div>
                </div>
                <div
                    class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400"
                >
                    <span>Win Rate Closing</span>
                    <span
                        class="font-bold text-emerald-700 dark:text-emerald-400"
                    >
                        {{ winRate === null ? '-' : `${winRate}% Won` }}
                    </span>
                </div>
            </div>

            <div
                class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >Estimasi Profit Bersih</span
                    >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        <span class="material-symbols-outlined text-[20px]"
                            >query_stats</span
                        >
                    </div>
                </div>
                <div class="mt-3">
                    <div
                        :class="[
                            'text-xl font-black tracking-tight sm:text-2xl',
                            netProfit < 0
                                ? 'text-rose-700 dark:text-rose-400'
                                : 'text-emerald-700 dark:text-emerald-400',
                        ]"
                    >
                        {{ formatRupiah(netProfit) }}
                    </div>
                    <div
                        class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Margin Bersih:
                        <span
                            class="font-bold text-slate-800 dark:text-slate-200"
                            >{{ profitMargin }}%</span
                        >
                    </div>
                </div>
                <div
                    class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400"
                >
                    <span>Beban Operasional</span>
                    <span
                        class="font-semibold text-rose-600 dark:text-rose-400"
                    >
                        {{ formatRupiahShort(totalExpense) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Main grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div class="space-y-6 lg:col-span-8">
                <!-- Active projects -->
                <div
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-slate-800"
                    >
                        <div>
                            <h2
                                class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100"
                            >
                                Status Proyek Berjalan &amp; Mendekati Deadline
                            </h2>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Pantau deliverable, progress, dan penanggung
                                jawab proyek.
                            </p>
                        </div>
                        <Link
                            :href="
                                projectsIndex.url({ current_team: teamSlug })
                            "
                            class="flex items-center gap-1 text-xs font-bold text-blue-700 hover:text-blue-800 dark:text-blue-400"
                        >
                            <span>Lihat Semua Proyek</span>
                            <span class="material-symbols-outlined text-[16px]"
                                >arrow_forward</span
                            >
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/75 font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">
                                        Nama Proyek &amp; Klien
                                    </th>
                                    <th class="px-3 py-3">Progress</th>
                                    <th class="px-3 py-3">Deadline</th>
                                    <th class="px-3 py-3">PIC</th>
                                    <th class="px-3 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr v-if="urgentProjects.length === 0">
                                    <td
                                        colspan="5"
                                        class="py-8 text-center text-slate-400"
                                    >
                                        Belum ada proyek aktif.
                                    </td>
                                </tr>

                                <tr
                                    v-for="project in urgentProjects"
                                    :key="project.id"
                                    class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                >
                                    <td class="max-w-[200px] px-4 py-3">
                                        <div
                                            class="truncate font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ project.name }}
                                        </div>
                                        <div
                                            class="truncate text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                project.clientName ??
                                                'Tanpa klien'
                                            }}
                                        </div>
                                    </td>
                                    <td class="min-w-[120px] px-3 py-3">
                                        <div
                                            class="mb-1 text-[11px] font-bold text-slate-700 dark:text-slate-300"
                                        >
                                            {{ project.progress }}%
                                        </div>
                                        <div
                                            class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                        >
                                            <div
                                                :class="[
                                                    'h-full rounded-full',
                                                    progressBarClass(
                                                        project.progress,
                                                    ),
                                                ]"
                                                :style="{
                                                    width: `${project.progress}%`,
                                                }"
                                            ></div>
                                        </div>
                                    </td>
                                    <td
                                        class="px-3 py-3 font-medium whitespace-nowrap text-slate-700 dark:text-slate-300"
                                    >
                                        {{ project.deadline ?? '-' }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <div
                                                class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                            >
                                                {{
                                                    (
                                                        project.picName ?? '?'
                                                    ).charAt(0)
                                                }}
                                            </div>
                                            <span
                                                class="max-w-[90px] truncate text-slate-700 dark:text-slate-300"
                                            >
                                                {{
                                                    project.picName ??
                                                    'Belum ada PIC'
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold',
                                                getProjectStatusBadge(
                                                    project.status,
                                                ).class,
                                            ]"
                                        >
                                            <span
                                                class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current"
                                            ></span>
                                            {{ project.statusLabel }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CRM funnel -->
                <div
                    class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2
                                class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100"
                            >
                                Pipeline Leads CRM Ringkas
                            </h2>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Distribusi prospek klien aktif di corong
                                penjualan.
                            </p>
                        </div>
                        <Link
                            :href="leadsIndex.url({ current_team: teamSlug })"
                            class="flex items-center gap-1 text-xs font-bold text-blue-700 hover:text-blue-800 dark:text-blue-400"
                        >
                            <span>Buka CRM</span>
                            <span class="material-symbols-outlined text-[16px]"
                                >arrow_forward</span
                            >
                        </Link>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3 text-center sm:grid-cols-4"
                    >
                        <div
                            v-for="bucket in funnel"
                            :key="bucket.label"
                            :class="['rounded-xl border p-3.5', bucket.class]"
                        >
                            <div class="mb-1 text-[11px] font-semibold">
                                {{ bucket.label }}
                            </div>
                            <div
                                class="text-xl font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ bucket.value }}
                            </div>
                            <div
                                class="mt-1 text-[10px] text-slate-500 dark:text-slate-400"
                            >
                                {{ bucket.hint }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6 lg:col-span-4">
                <!-- Content schedule -->
                <div
                    class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3
                                class="text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100"
                            >
                                Jadwal Konten Medsos
                            </h3>
                            <p
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Pipeline editorial publikasi terbaru.
                            </p>
                        </div>
                        <Link
                            :href="
                                contentsIndex.url({ current_team: teamSlug })
                            "
                            class="text-[11px] font-bold text-blue-700 hover:text-blue-800 dark:text-blue-400"
                        >
                            Lihat Kalender
                        </Link>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-if="scheduledContents.length === 0"
                            class="py-4 text-center text-xs text-slate-400"
                        >
                            Belum ada rencana konten.
                        </div>

                        <div
                            v-for="item in scheduledContents"
                            :key="item.id"
                            class="rounded-xl border border-slate-100 bg-slate-50/40 p-3 text-xs transition-all hover:border-blue-200 hover:bg-white dark:border-slate-800 dark:bg-slate-800/40"
                        >
                            <div
                                class="mb-1.5 flex items-center justify-between"
                            >
                                <span
                                    class="text-[10px] font-bold tracking-wider text-blue-700 uppercase dark:text-blue-400"
                                >
                                    {{ item.platform }}
                                </span>
                                <span
                                    :class="[
                                        'rounded-md px-2 py-0.5 text-[10px] font-semibold',
                                        getContentStatusBadge(item.status)
                                            .class,
                                    ]"
                                >
                                    {{ item.statusLabel }}
                                </span>
                            </div>
                            <div
                                class="mb-1 line-clamp-1 font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ item.title }}
                            </div>
                            <div
                                class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                <span class="flex items-center gap-1">
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        >schedule</span
                                    >
                                    {{
                                        item.scheduledAt ?? 'Belum dijadwalkan'
                                    }}
                                </span>
                                <span>{{
                                    item.assigneeName ?? 'Tim Media Sosial'
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity log -->
                <div
                    class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3
                                class="text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100"
                            >
                                Aktivitas &amp; Log Sistem
                            </h3>
                            <p
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Audit trail kegiatan tim.
                            </p>
                        </div>
                        <span
                            class="material-symbols-outlined text-[18px] text-slate-400"
                            >history</span
                        >
                    </div>

                    <div class="space-y-4">
                        <div
                            v-if="activityLogs.length === 0"
                            class="py-4 text-center text-xs text-slate-400"
                        >
                            Belum ada aktivitas.
                        </div>

                        <div
                            v-for="log in activityLogs"
                            :key="log.id"
                            class="flex gap-3 text-xs"
                        >
                            <div
                                class="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <span
                                    class="material-symbols-outlined text-[16px]"
                                    >{{ logIcon(log.entityType) }}</span
                                >
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ log.action }}
                                </div>
                                <div
                                    class="mt-0.5 line-clamp-2 text-[11px] leading-relaxed text-slate-600 dark:text-slate-300"
                                >
                                    {{ log.details }}
                                </div>
                                <div class="mt-1 text-[10px] text-slate-400">
                                    {{ log.performedByName ?? 'Sistem' }} &bull;
                                    <span
                                        :title="formatDateTimeId(log.createdAt)"
                                    >
                                        {{
                                            mounted
                                                ? formatTimeAgo(log.createdAt)
                                                : formatDateTimeId(
                                                      log.createdAt,
                                                  )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
