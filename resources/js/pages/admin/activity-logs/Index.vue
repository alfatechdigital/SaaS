<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import type { ActivityEntityType, ActivityLog } from '@/types';
import { formatDateTimeId, formatTimeAgo, getActivityActionBadge } from '@/utils/formatters';

/**
 * Audit trail of every tracked data change.
 *
 * Ported from `ActivityLogsPage.tsx`. Differences from the template:
 * - The filter chips are derived from the entity types present in the data, and
 *   their labels come from the server (`entityTypeLabel`) instead of a hardcoded
 *   second list. The template's lists disagreed with each other ("finance" and
 *   "mediasos" were never real entity values).
 * - The timeline badge colour comes from `actionType` (a server-classified
 *   `created`/`updated`/`deleted`), not from matching the Indonesian sentence.
 * - Relative time ("3 jam lalu") depends on the clock, so it is only rendered
 *   after mount; the server render shows the absolute timestamp instead. That
 *   keeps the SSR output and the first client render identical (ADR-19).
 */
const props = defineProps<{
    logs: ActivityLog[];
}>();

const entityFilter = ref<'all' | ActivityEntityType>('all');
const searchQuery = ref('');
const mounted = ref(false);

onMounted(() => {
    mounted.value = true;
});

const entityFilters = computed(() => {
    const seen = new Map<ActivityEntityType, string>();

    props.logs.forEach((log) => {
        if (!seen.has(log.entityType)) {
            seen.set(log.entityType, log.entityTypeLabel);
        }
    });

    return Array.from(seen, ([value, label]) => ({ value, label }));
});

const filteredLogs = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.logs.filter((log) => {
        if (entityFilter.value !== 'all' && log.entityType !== entityFilter.value) {
            return false;
        }

        if (!query) {
            return true;
        }

        return (
            (log.performedByName ?? '').toLowerCase().includes(query) ||
            (log.details ?? '').toLowerCase().includes(query)
        );
    });
});
</script>

<template>
    <Head title="Log Aktivitas" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100">
                    Log Aktivitas &amp; Audit Perubahan
                </h1>
                <p class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                    Rekam jejak setiap perubahan data untuk transparansi manajemen. Menampilkan
                    {{ logs.length }} aktivitas terbaru.
                </p>
            </div>
        </div>

        <!-- Filters & search -->
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-semibold sm:text-sm">
                <button
                    :class="[
                        'rounded-lg px-3 py-1.5 whitespace-nowrap transition-all',
                        entityFilter === 'all'
                            ? 'bg-[#1e40af] font-bold text-white shadow-xs'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                    ]"
                    @click="entityFilter = 'all'"
                >
                    Semua Aktivitas
                </button>
                <button
                    v-for="filter in entityFilters"
                    :key="filter.value"
                    :class="[
                        'rounded-lg px-3 py-1.5 whitespace-nowrap transition-all',
                        entityFilter === filter.value
                            ? 'bg-[#1e40af] font-bold text-white shadow-xs'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                    ]"
                    @click="entityFilter = filter.value"
                >
                    {{ filter.label }}
                </button>
            </div>

            <div class="relative w-full sm:w-64">
                <span class="material-symbols-outlined absolute top-2 left-3 text-[18px] text-slate-400">search</span>
                <input
                    v-model="searchQuery"
                    placeholder="Cari pelaku atau aktivitas..."
                    class="h-9 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                />
            </div>
        </div>

        <!-- Timeline -->
        <div
            class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div v-if="filteredLogs.length === 0" class="py-10 text-center text-sm text-slate-400">
                Belum ada aktivitas yang cocok.
            </div>

            <div v-else class="relative ml-3.5 space-y-6 border-l-2 border-slate-100 dark:border-slate-800">
                <div v-for="log in filteredLogs" :key="log.id" class="relative pl-6 sm:pl-8">
                    <span
                        class="absolute top-1 -left-[9px] h-4 w-4 rounded-full border-4 border-blue-600 bg-white dark:bg-slate-900"
                    ></span>

                    <div
                        class="space-y-1.5 rounded-xl border border-slate-100 bg-slate-50/70 p-3.5 sm:p-4 dark:border-slate-800 dark:bg-slate-800/40"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-900 sm:text-sm dark:text-slate-100">
                                    {{ log.performedByName ?? 'Sistem' }}
                                </span>
                                <span
                                    :class="[
                                        'rounded px-2 py-0.5 text-[10px] font-bold uppercase',
                                        getActivityActionBadge(log.actionType),
                                    ]"
                                >
                                    {{ log.action }}
                                </span>
                                <span
                                    class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 uppercase dark:bg-slate-700 dark:text-slate-300"
                                >
                                    {{ log.entityTypeLabel }}
                                </span>
                            </div>
                            <span
                                class="text-[11px] font-medium text-slate-400"
                                :title="formatDateTimeId(log.createdAt)"
                            >
                                {{ mounted ? formatTimeAgo(log.createdAt) : formatDateTimeId(log.createdAt) }}
                            </span>
                        </div>

                        <p class="text-xs leading-relaxed font-medium text-slate-700 dark:text-slate-300">
                            {{ log.details }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
