<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import {
    convert as leadConvert,
    destroy as leadDestroy,
    store as leadStore,
    update as leadUpdate,
} from '@/routes/leads';
import type { Lead, LeadStatus } from '@/types';
import {
    formatRupiah,
    formatRupiahShort,
    getLeadStatusBadge,
} from '@/utils/formatters';

/**
 * Sales pipeline: Kanban board + table view.
 *
 * Ported from `LeadsPage.tsx`. Differences from the template:
 * - The "Unduh Estimasi" button really exports a CSV instead of calling a stub
 *   `alert()`.
 * - The "Follow Up Mendesak Hari Ini" banner listed three hardcoded fake leads.
 *   It now lists real leads that still have a `nextFollowUp` value.
 * - "Closing Win Rate" was hardcoded to 68.4% with a fake Q4 target. It is now
 *   computed from the won/lost leads, and shows `-` while nothing is closed.
 * - A 6th Kanban column for "Lost" was added. The template dropped lost leads
 *   from the board entirely, so they silently vanished in Kanban view.
 * - Source filter options are derived from the real data instead of a fixed list.
 * - Badge text comes from the Resource (`statusLabel`), colours from
 *   `getLeadStatusBadge()`.
 */
const props = defineProps<{
    leads: Lead[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const viewMode = ref<'kanban' | 'table'>('kanban');
const searchQuery = ref('');
const sourceFilter = ref('all');

const isModalOpen = ref(false);
const editingLead = ref<Lead | null>(null);
const convertingId = ref<number | null>(null);

const sources = computed(() => [
    'all',
    ...Array.from(
        new Set(
            props.leads
                .map((lead) => lead.source)
                .filter((source): source is string => !!source),
        ),
    ),
]);

const filteredLeads = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.leads.filter((lead) => {
        if (
            sourceFilter.value !== 'all' &&
            lead.source !== sourceFilter.value
        ) {
            return false;
        }

        if (!query) {
            return true;
        }

        return (
            lead.companyName.toLowerCase().includes(query) ||
            (lead.contactName ?? '').toLowerCase().includes(query) ||
            (lead.potentialProject ?? '').toLowerCase().includes(query)
        );
    });
});

const totalPipelineValue = computed(() =>
    props.leads.reduce((total, lead) => total + lead.estimatedValue, 0),
);

const newLeadsCount = computed(
    () => props.leads.filter((lead) => lead.status === 'new').length,
);

const negotiationLeads = computed(() =>
    props.leads.filter(
        (lead) => lead.status === 'proposal' || lead.status === 'negotiation',
    ),
);

const negotiationValue = computed(() =>
    negotiationLeads.value.reduce(
        (total, lead) => total + lead.estimatedValue,
        0,
    ),
);

const closedLeads = computed(() =>
    props.leads.filter(
        (lead) => lead.status === 'won' || lead.status === 'lost',
    ),
);

const wonLeadCount = computed(
    () => props.leads.filter((lead) => lead.status === 'won').length,
);

const winRate = computed(() => {
    if (closedLeads.value.length === 0) {
        return null;
    }

    return (wonLeadCount.value / closedLeads.value.length) * 100;
});

/**
 * Leads that still need a follow up. `next_follow_up` is free text, so this
 * cannot judge whether the date has passed — it only surfaces open leads that
 * have one configured.
 */
const pendingFollowUps = computed(() =>
    props.leads
        .filter(
            (lead) =>
                !!lead.nextFollowUp &&
                lead.status !== 'won' &&
                lead.status !== 'lost',
        )
        .slice(0, 3),
);

const kanbanColumns: {
    id: string;
    title: string;
    statuses: LeadStatus[];
    accent: string;
}[] = [
    {
        id: 'new',
        title: 'Prospek Baru',
        statuses: ['new'],
        accent: 'border-t-blue-500',
    },
    {
        id: 'contacted',
        title: 'Dihubungi',
        statuses: ['contacted'],
        accent: 'border-t-sky-500',
    },
    {
        id: 'meeting',
        title: 'Jadwal Meeting',
        statuses: ['follow_up', 'meeting'],
        accent: 'border-t-amber-500',
    },
    {
        id: 'negotiation',
        title: 'Proposal & Negosiasi',
        statuses: ['proposal', 'negotiation'],
        accent: 'border-t-indigo-500',
    },
    {
        id: 'won',
        title: 'Won / Deal',
        statuses: ['won'],
        accent: 'border-t-emerald-500',
    },
    {
        id: 'lost',
        title: 'Lost / Batal',
        statuses: ['lost'],
        accent: 'border-t-rose-500',
    },
];

function columnLeads(statuses: LeadStatus[]): Lead[] {
    return filteredLeads.value.filter((lead) => statuses.includes(lead.status));
}

function columnValue(statuses: LeadStatus[]): number {
    return columnLeads(statuses).reduce(
        (total, lead) => total + lead.estimatedValue,
        0,
    );
}

function whatsappLink(phone: string | null): string | null {
    const digits = (phone ?? '').replace(/[^0-9]/g, '').replace(/^0/, '62');

    return digits ? `https://wa.me/${digits}` : null;
}

const statusOptions: { value: LeadStatus; label: string }[] = [
    { value: 'new', label: 'Prospek Baru' },
    { value: 'contacted', label: 'Dihubungi' },
    { value: 'follow_up', label: 'Follow Up' },
    { value: 'meeting', label: 'Jadwal Meeting' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'negotiation', label: 'Negosiasi' },
    { value: 'won', label: 'Won (Deal)' },
    { value: 'lost', label: 'Lost (Batal)' },
];

const inputClass =
    'w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none';
const textareaClass =
    'w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none leading-relaxed';
const labelClass =
    'block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1';

const form = useForm({
    company_name: '',
    contact_name: '',
    phone: '',
    email: '',
    source: '',
    potential_project: '',
    estimated_value: 30_000_000,
    status: 'new' as LeadStatus,
    next_follow_up: 'Besok',
    notes: '',
});

function openCreateModal(): void {
    editingLead.value = null;
    form.clearErrors();
    form.company_name = '';
    form.contact_name = '';
    form.phone = '';
    form.email = '';
    form.source = sources.value[1] ?? 'Website Alfatech';
    form.potential_project = '';
    form.estimated_value = 30_000_000;
    form.status = 'new';
    form.next_follow_up = 'Besok';
    form.notes = '';
    isModalOpen.value = true;
}

function openEditModal(lead: Lead): void {
    editingLead.value = lead;

    form.company_name = lead.companyName;
    form.contact_name = lead.contactName ?? '';
    form.phone = lead.phone ?? '';
    form.email = lead.email ?? '';
    form.source = lead.source ?? '';
    form.potential_project = lead.potentialProject ?? '';
    form.estimated_value = lead.estimatedValue;
    form.status = lead.status;
    form.next_follow_up = lead.nextFollowUp ?? '';
    form.notes = lead.notes ?? '';

    form.clearErrors();
    isModalOpen.value = true;
}

function saveLead(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isModalOpen.value = false;
            editingLead.value = null;
        },
    };

    if (editingLead.value) {
        form.put(
            leadUpdate.url({
                current_team: teamSlug.value,
                lead: editingLead.value.id,
            }),
            options,
        );

        return;
    }

    form.post(leadStore.url({ current_team: teamSlug.value }), options);
}

function changeStatus(lead: Lead, status: LeadStatus): void {
    if (lead.status === status) {
        return;
    }

    router.put(
        leadUpdate.url({ current_team: teamSlug.value, lead: lead.id }),
        {
            company_name: lead.companyName,
            contact_name: lead.contactName,
            phone: lead.phone,
            email: lead.email,
            source: lead.source,
            potential_project: lead.potentialProject,
            estimated_value: lead.estimatedValue,
            status,
            next_follow_up: lead.nextFollowUp,
            notes: lead.notes,
        },
        { preserveScroll: true },
    );
}

function removeLead(lead: Lead): void {
    if (!window.confirm(`Hapus prospek "${lead.companyName}"?`)) {
        return;
    }

    router.delete(
        leadDestroy.url({ current_team: teamSlug.value, lead: lead.id }),
        {
            preserveScroll: true,
        },
    );
}

function convertLead(lead: Lead): void {
    if (
        !window.confirm(
            `Konversi deal "${lead.companyName}" menjadi proyek pengembangan?`,
        )
    ) {
        return;
    }

    convertingId.value = lead.id;

    router.post(
        leadConvert.url({ current_team: teamSlug.value, lead: lead.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                convertingId.value = null;
            },
        },
    );
}

function exportCsv(): void {
    const header = [
        'Instansi',
        'Kontak',
        'Telepon',
        'Email',
        'Sumber',
        'Kebutuhan',
        'Estimasi Kontrak',
        'Status',
        'Follow Up',
    ];

    const rows = filteredLeads.value.map((lead) => [
        lead.companyName,
        lead.contactName ?? '',
        lead.phone ?? '',
        lead.email ?? '',
        lead.source ?? '',
        lead.potentialProject ?? '',
        String(lead.estimatedValue),
        lead.statusLabel,
        lead.nextFollowUp ?? '',
    ]);

    const csv = [header, ...rows]
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
    link.download = `pipeline-leads-${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Pipeline Leads" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                >
                    Pipeline Leads &amp; CRM Klien
                </h1>
                <p
                    class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    Kelola prospek inbound, tahapan negosiasi, follow up, dan
                    konversi ke proyek aktif.
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200 sm:text-sm dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    @click="exportCsv"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >download</span
                    >
                    <span class="hidden sm:inline">Unduh Estimasi</span>
                </button>
                <button
                    class="flex items-center gap-1.5 rounded-xl bg-[#1e40af] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-700 sm:text-sm"
                    @click="openCreateModal"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >person_add</span
                    >
                    <span>+ Tambah Prospek Baru</span>
                </button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="rounded-2xl border border-slate-100 bg-white p-4.5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    Total Nilai Pipeline
                </div>
                <div
                    class="mt-1.5 text-xl font-bold text-slate-900 dark:text-slate-100"
                >
                    {{ formatRupiah(totalPipelineValue) }}
                </div>
                <div
                    class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                >
                    {{ leads.length }} Calon Klien Aktif
                </div>
            </div>

            <div
                class="rounded-2xl border border-slate-100 bg-white p-4.5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    Prospek Baru
                </div>
                <div
                    class="mt-1.5 text-xl font-bold text-blue-700 dark:text-blue-400"
                >
                    {{ newLeadsCount }} Leads
                </div>
                <div
                    class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                >
                    Belum dihubungi
                </div>
            </div>

            <div
                class="rounded-2xl border border-slate-100 bg-white p-4.5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    Tahap Negosiasi &amp; Proposal
                </div>
                <div
                    class="mt-1.5 text-xl font-bold text-indigo-700 dark:text-indigo-400"
                >
                    {{ negotiationLeads.length }} Leads
                </div>
                <div
                    class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                >
                    {{ formatRupiahShort(negotiationValue) }} mendekati deal
                </div>
            </div>

            <div
                class="rounded-2xl border border-slate-100 bg-white p-4.5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    Closing Win Rate
                </div>
                <div
                    class="mt-1.5 text-xl font-bold text-emerald-700 dark:text-emerald-400"
                >
                    {{ winRate === null ? '-' : `${winRate.toFixed(1)}%` }}
                </div>
                <div
                    class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                >
                    {{
                        closedLeads.length === 0
                            ? 'Belum ada lead ditutup'
                            : `${wonLeadCount} menang dari ${closedLeads.length} lead ditutup`
                    }}
                </div>
            </div>
        </div>

        <!-- Pending follow ups (real data) -->
        <div
            v-if="pendingFollowUps.length > 0"
            class="flex flex-col justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50/75 p-4 text-xs text-amber-900 sm:flex-row sm:items-center dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white"
                >
                    <span class="material-symbols-outlined text-[20px]"
                        >notification_important</span
                    >
                </div>
                <div>
                    <span class="font-bold text-amber-950 dark:text-amber-100"
                        >Follow Up Terjadwal:
                    </span>
                    <span>
                        {{
                            pendingFollowUps
                                .map(
                                    (lead) =>
                                        `${lead.companyName} (${lead.nextFollowUp})`,
                                )
                                .join(', ')
                        }}
                        <template
                            v-if="
                                leads.filter(
                                    (l) =>
                                        l.nextFollowUp &&
                                        l.status !== 'won' &&
                                        l.status !== 'lost',
                                ).length > 3
                            "
                        >
                            , dan lainnya
                        </template>
                    </span>
                </div>
            </div>
        </div>

        <!-- Search, filter, view switcher -->
        <div
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div class="flex max-w-xl flex-1 items-center gap-2.5">
                <div class="relative flex-1">
                    <span
                        class="material-symbols-outlined absolute top-2.5 left-3 text-[18px] text-slate-400"
                        >search</span
                    >
                    <input
                        v-model="searchQuery"
                        placeholder="Cari nama prospek, instansi, atau PIC..."
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    />
                </div>

                <select
                    v-model="sourceFilter"
                    :class="[
                        inputClass,
                        'h-10 cursor-pointer! text-slate-700 dark:text-slate-300',
                    ]"
                >
                    <option value="all">Semua Sumber</option>
                    <option
                        v-for="source in sources.slice(1)"
                        :key="source"
                        :value="source"
                    >
                        {{ source }}
                    </option>
                </select>
            </div>

            <div
                class="flex items-center self-start rounded-xl bg-slate-100 p-1 sm:self-auto dark:bg-slate-800"
            >
                <button
                    :class="[
                        'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                        viewMode === 'kanban'
                            ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-100'
                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100',
                    ]"
                    @click="viewMode = 'kanban'"
                >
                    <span class="material-symbols-outlined text-[16px]"
                        >view_kanban</span
                    >
                    <span>Kanban Board</span>
                </button>
                <button
                    :class="[
                        'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                        viewMode === 'table'
                            ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-100'
                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100',
                    ]"
                    @click="viewMode = 'table'"
                >
                    <span class="material-symbols-outlined text-[16px]"
                        >table_rows</span
                    >
                    <span>Tabel View</span>
                </button>
            </div>
        </div>

        <!-- Kanban -->
        <div
            v-if="viewMode === 'kanban'"
            class="grid grid-cols-1 items-start gap-4 md:grid-cols-3 lg:grid-cols-6"
        >
            <div
                v-for="column in kanbanColumns"
                :key="column.id"
                :class="[
                    'space-y-3 rounded-2xl border border-t-4 border-slate-200 bg-slate-50/75 p-3.5 dark:border-slate-800 dark:bg-slate-900/60',
                    column.accent,
                ]"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3
                            class="text-xs font-bold tracking-wide text-slate-900 uppercase dark:text-slate-100"
                        >
                            {{ column.title }}
                        </h3>
                        <div
                            class="mt-0.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                        >
                            {{
                                formatRupiahShort(columnValue(column.statuses))
                            }}
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-slate-700 shadow-xs dark:bg-slate-800 dark:text-slate-200"
                    >
                        {{ columnLeads(column.statuses).length }}
                    </span>
                </div>

                <div class="min-h-[140px] space-y-3">
                    <div
                        v-if="columnLeads(column.statuses).length === 0"
                        class="flex h-24 items-center justify-center rounded-xl border border-dashed border-slate-200 text-[11px] text-slate-400 dark:border-slate-700"
                    >
                        Kosong
                    </div>

                    <div
                        v-for="lead in columnLeads(column.statuses)"
                        :key="lead.id"
                        class="space-y-2.5 rounded-xl border border-slate-100 bg-white p-3.5 shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <div
                                class="text-xs font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ lead.companyName }}
                            </div>
                            <div
                                class="mt-0.5 line-clamp-1 text-[11px] font-medium text-blue-700 dark:text-blue-400"
                            >
                                {{
                                    lead.potentialProject ??
                                    'Belum ada kebutuhan tercatat'
                                }}
                            </div>
                        </div>

                        <div
                            class="space-y-1 rounded-lg border border-slate-100 bg-slate-50 p-2 text-[11px] text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-medium text-slate-700 dark:text-slate-200"
                                >
                                    {{ lead.contactName ?? 'Tanpa kontak' }}
                                </span>
                                <a
                                    v-if="whatsappLink(lead.phone)"
                                    :href="whatsappLink(lead.phone)!"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="flex items-center gap-0.5 font-bold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400"
                                >
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        >chat</span
                                    >
                                    <span>WA</span>
                                </a>
                            </div>
                            <div class="text-[10px] text-slate-400">
                                {{ lead.source ?? '-' }}
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between pt-1 text-xs"
                        >
                            <span
                                class="font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ formatRupiah(lead.estimatedValue) }}
                            </span>
                            <span
                                v-if="lead.nextFollowUp"
                                class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                            >
                                {{ lead.nextFollowUp }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-1 border-t border-slate-100 pt-2 dark:border-slate-800"
                        >
                            <button
                                v-if="lead.status === 'won'"
                                :disabled="convertingId === lead.id"
                                class="flex w-full items-center justify-center gap-1 rounded-lg bg-emerald-600 px-2 py-1.5 text-[11px] font-bold text-white shadow-xs transition-colors hover:bg-emerald-700 disabled:opacity-50"
                                @click="convertLead(lead)"
                            >
                                <span
                                    class="material-symbols-outlined text-[14px]"
                                    >rocket_launch</span
                                >
                                <span>{{
                                    convertingId === lead.id
                                        ? 'Memproses...'
                                        : 'Konversi ke Proyek'
                                }}</span>
                            </button>

                            <template v-else>
                                <select
                                    :value="lead.status"
                                    class="cursor-pointer rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-700 hover:bg-slate-200 focus:outline-none dark:bg-slate-800 dark:text-slate-200"
                                    @change="
                                        changeStatus(
                                            lead,
                                            ($event.target as HTMLSelectElement)
                                                .value as LeadStatus,
                                        )
                                    "
                                >
                                    <option
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        Tahap: {{ option.label }}
                                    </option>
                                </select>

                                <div class="flex items-center gap-1">
                                    <button
                                        title="Edit Lead"
                                        class="p-1 text-slate-400 hover:text-blue-600"
                                        @click="openEditModal(lead)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[16px]"
                                            >edit</span
                                        >
                                    </button>
                                    <button
                                        title="Hapus Lead"
                                        class="p-1 text-slate-400 hover:text-rose-600"
                                        @click="removeLead(lead)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[16px]"
                                            >delete</span
                                        >
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table view -->
        <div
            v-else
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-slate-100 bg-slate-50 font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-4 py-3.5">
                                Instansi &amp; Kebutuhan
                            </th>
                            <th class="px-3 py-3.5">Kontak &amp; No. Telp</th>
                            <th class="px-3 py-3.5">Sumber Leads</th>
                            <th class="px-3 py-3.5">Estimasi Kontrak</th>
                            <th class="px-3 py-3.5">Status Corong</th>
                            <th class="px-3 py-3.5">Follow Up</th>
                            <th class="px-3 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr v-if="filteredLeads.length === 0">
                            <td
                                colspan="7"
                                class="py-8 text-center text-slate-400"
                            >
                                Tidak ada prospek yang sesuai dengan kriteria
                                filter.
                            </td>
                        </tr>

                        <tr
                            v-for="lead in filteredLeads"
                            :key="lead.id"
                            class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-4 py-3">
                                <div
                                    class="font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ lead.companyName }}
                                </div>
                                <div
                                    class="text-[11px] text-blue-700 dark:text-blue-400"
                                >
                                    {{ lead.potentialProject ?? '-' }}
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div
                                    class="font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ lead.contactName ?? '-' }}
                                </div>
                                <a
                                    v-if="whatsappLink(lead.phone)"
                                    :href="whatsappLink(lead.phone)!"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="flex items-center gap-0.5 text-[11px] text-emerald-700 hover:underline dark:text-emerald-400"
                                >
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        >call</span
                                    >
                                    <span>{{ lead.phone }}</span>
                                </a>
                                <span v-else class="text-[11px] text-slate-400"
                                    >-</span
                                >
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ lead.source ?? '-' }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-3 font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ formatRupiah(lead.estimatedValue) }}
                            </td>
                            <td class="px-3 py-3">
                                <select
                                    :value="lead.status"
                                    :class="[
                                        'cursor-pointer rounded-md border px-2 py-1 text-xs font-semibold focus:outline-none',
                                        getLeadStatusBadge(lead.status).class,
                                    ]"
                                    @change="
                                        changeStatus(
                                            lead,
                                            ($event.target as HTMLSelectElement)
                                                .value as LeadStatus,
                                        )
                                    "
                                >
                                    <option
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </td>
                            <td
                                class="px-3 py-3 font-medium text-slate-600 dark:text-slate-300"
                            >
                                {{ lead.nextFollowUp ?? '-' }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1.5"
                                >
                                    <button
                                        v-if="lead.status === 'won'"
                                        :disabled="convertingId === lead.id"
                                        title="Konversi ke Proyek"
                                        class="flex items-center gap-1 rounded bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                                        @click="convertLead(lead)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[14px]"
                                            >rocket_launch</span
                                        >
                                        <span>Proyek</span>
                                    </button>
                                    <button
                                        class="rounded p-1 text-slate-400 hover:text-blue-600"
                                        @click="openEditModal(lead)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[18px]"
                                            >edit</span
                                        >
                                    </button>
                                    <button
                                        class="rounded p-1 text-slate-400 hover:text-rose-600"
                                        @click="removeLead(lead)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[18px]"
                                            >delete</span
                                        >
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: create / edit lead -->
        <Modal
            :is-open="isModalOpen"
            :title="
                editingLead ? 'Edit Prospek Leads' : 'Tambah Prospek Klien Baru'
            "
            subtitle="Masukkan detail kebutuhan calon klien untuk corong penjualan."
            @close="isModalOpen = false"
        >
            <form
                class="space-y-4 text-xs sm:text-sm"
                @submit.prevent="saveLead"
            >
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass"
                            >Nama Instansi / Perusahaan *</label
                        >
                        <input
                            v-model="form.company_name"
                            required
                            placeholder="Contoh: PT Graha Finansial"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.company_name"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.company_name }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass"
                            >Nama Kontak Person (PIC Klien)</label
                        >
                        <input
                            v-model="form.contact_name"
                            placeholder="Contoh: Pak Hendra (Direktur)"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.contact_name"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.contact_name }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass"
                            >No. WhatsApp / Telepon</label
                        >
                        <input
                            v-model="form.phone"
                            placeholder="0812-8899-xxxx"
                            :class="inputClass"
                        />
                    </div>

                    <div>
                        <label :class="labelClass">Email Klien</label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="klien@perusahaan.com"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.email"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass"
                            >Kebutuhan Proyek yang Diminati</label
                        >
                        <input
                            v-model="form.potential_project"
                            placeholder="Contoh: Web Portal Investasi & CRM"
                            :class="inputClass"
                        />
                    </div>

                    <div>
                        <label :class="labelClass">Sumber Leads</label>
                        <input
                            v-model="form.source"
                            list="lead-sources"
                            placeholder="Website Alfatech"
                            :class="inputClass"
                        />
                        <datalist id="lead-sources">
                            <option
                                v-for="source in sources.slice(1)"
                                :key="source"
                                :value="source"
                            />
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label :class="labelClass"
                            >Estimasi Nilai Kontrak (Rp)</label
                        >
                        <input
                            v-model.number="form.estimated_value"
                            type="number"
                            min="0"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.estimated_value"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.estimated_value }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Status Tahapan CRM</label>
                        <select
                            v-model="form.status"
                            :class="[inputClass, 'cursor-pointer']"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label :class="labelClass">Jadwal Follow Up</label>
                        <input
                            v-model="form.next_follow_up"
                            placeholder="Contoh: Besok, 10:00 WIB"
                            :class="inputClass"
                        />
                    </div>
                </div>

                <div>
                    <label :class="labelClass"
                        >Catatan Kebutuhan &amp; Hasil Diskusi</label
                    >
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Catatan hasil percakapan atau requirement khusus klien..."
                        :class="textareaClass"
                    />
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
                            form.processing ? 'Menyimpan...' : 'Simpan Prospek'
                        }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
