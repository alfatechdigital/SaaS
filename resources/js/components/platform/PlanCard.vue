<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { update } from '@/routes/platform/plans';
import type { PlatformPlanRow } from '@/types/billing';

/**
 * One plan in the platform panel (F-8).
 *
 * Only the commercial half is editable — price, description, on sale. Features and
 * quotas are rendered from `config/plans.php` because that is where entitlement
 * reads them (ADR-14, D-06); an editable field here would promise runtime changes
 * the application would ignore.
 */
const props = defineProps<{
    plan: PlatformPlanRow;
}>();

const form = useForm({
    description: props.plan.description ?? '',
    price_monthly: props.plan.priceMonthly,
    price_yearly: props.plan.priceYearly,
    is_active: props.plan.isActive,
});

const limitLabels: Record<string, string> = {
    users: 'Pengguna',
    ai_generations_per_month: 'AI generate / bulan',
    whatsapp_messages_per_month: 'Pesan WhatsApp / bulan',
};

function submit(): void {
    form.patch(update.url(props.plan.id), { preserveScroll: true });
}
</script>

<template>
    <div
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2
                        class="text-lg font-bold text-slate-900 dark:text-slate-100"
                    >
                        {{ plan.name }}
                    </h2>
                    <span
                        class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ plan.code }}
                    </span>
                    <span
                        v-if="plan.isDefault"
                        class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                    >
                        Default
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Fitur &amp; kuota dibaca dari
                    <code>config/plans.php</code> — hanya-baca.
                </p>
            </div>

            <span
                class="rounded-full px-3 py-1 text-[11px] font-bold"
                :class="
                    plan.isActive
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                "
            >
                {{ plan.isActive ? 'Dijual' : 'Tidak dijual' }}
            </span>
        </div>

        <form
            class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="sm:col-span-3">
                <label
                    :for="`description-${plan.id}`"
                    class="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300"
                >
                    Deskripsi
                </label>
                <input
                    :id="`description-${plan.id}`"
                    v-model="form.description"
                    type="text"
                    maxlength="255"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
                <InputError :message="form.errors.description" class="mt-1" />
            </div>

            <div>
                <label
                    :for="`monthly-${plan.id}`"
                    class="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300"
                >
                    Harga bulanan (Rp)
                </label>
                <input
                    :id="`monthly-${plan.id}`"
                    v-model.number="form.price_monthly"
                    type="number"
                    min="0"
                    step="1000"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
                <InputError :message="form.errors.price_monthly" class="mt-1" />
            </div>

            <div>
                <label
                    :for="`yearly-${plan.id}`"
                    class="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300"
                >
                    Harga tahunan (Rp)
                </label>
                <input
                    :id="`yearly-${plan.id}`"
                    v-model.number="form.price_yearly"
                    type="number"
                    min="0"
                    step="1000"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
                <InputError :message="form.errors.price_yearly" class="mt-1" />
            </div>

            <div class="flex items-end justify-between gap-3">
                <label
                    class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600"
                    />
                    Dijual
                </label>
                <button
                    type="submit"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-blue-700 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </button>
            </div>
        </form>
        <InputError :message="form.errors.is_active" class="mt-1" />

        <div
            class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-slate-800"
        >
            <div>
                <h3
                    class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                >
                    Fitur ({{ plan.features.length }})
                </h3>
                <ul class="mt-2 flex flex-wrap gap-1.5">
                    <li
                        v-for="feature in plan.features"
                        :key="feature.value"
                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ feature.label }}
                    </li>
                </ul>
            </div>

            <div>
                <h3
                    class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                >
                    Kuota
                </h3>
                <ul
                    class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300"
                >
                    <li
                        v-for="(limit, metric) in plan.limits"
                        :key="metric"
                        class="flex justify-between gap-3"
                    >
                        <span>{{ limitLabels[metric] ?? metric }}</span>
                        <span class="font-bold">{{ limit }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
