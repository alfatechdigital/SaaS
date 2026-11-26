<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlanCard from '@/components/platform/PlanCard.vue';
import type { PlatformPlanRow } from '@/types/billing';

/**
 * Plan catalogue for the platform operator (F-8, B-2 tugas 26 Nov).
 *
 * Prices and descriptions are editable because they live in the `plans` table;
 * features and quotas are shown read-only because entitlement reads
 * `config/plans.php` (ADR-14, D-06). `PlanSeeder` still copies config into the
 * table, so re-running it overwrites manual price edits — the page warns instead
 * of hiding that.
 */
defineProps<{
    plans: PlatformPlanRow[];
}>();
</script>

<template>
    <Head title="Paket" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                Paket langganan
            </h1>
            <p
                class="mt-1 max-w-3xl text-sm text-slate-600 dark:text-slate-400"
            >
                Harga dan status penjualan bisa diubah di sini. Fitur dan kuota
                tetap berasal dari <code>config/plans.php</code> (ADR-14, D-06),
                jadi perubahan kontrak paket masih lewat deploy.
            </p>
            <p
                class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <code>php artisan db:seed --class=PlanSeeder</code> menyalin
                ulang harga dari config dan akan menimpa perubahan manual di
                halaman ini.
            </p>
        </div>

        <PlanCard v-for="plan in plans" :key="plan.id" :plan="plan" />
    </div>
</template>
