<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useAppearance, type Appearance } from '@/composables/useAppearance';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';
import { index as teamIndex } from '@/routes/team';

/**
 * Account & system preferences overview.
 *
 * Ported from `SettingsPage.tsx`. Three deviations were necessary:
 *
 * 1. The template's own theme switcher is replaced by `useAppearance()`, which
 *    the rest of the Alfatech shell already uses (cookie + localStorage, with
 *    `HandleAppearance` reading it server-side). Having two independent theme
 *    stores would drift apart.
 * 2. The "Cloud Firestore connection" panel is gone — there is no Firestore any
 *    more, so the panel now reports the real stack (`system` prop) instead of
 *    claiming a connection that does not exist.
 * 3. The active theme is only highlighted after mount. `useAppearance()` reads
 *    localStorage on mount, so rendering the selection during SSR would produce
 *    server/client markup that disagrees (ADR-19). The cards themselves render
 *    identically on both sides; only the highlight waits for mount.
 */
const props = defineProps<{
    system: {
        database: string;
        cache: string;
        queue: string;
        session: string;
        mail: string;
        laravel: string;
        php: string;
    };
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const mounted = ref(false);

onMounted(() => {
    mounted.value = true;
});

const user = computed(() => page.props.auth.user);
const team = computed(() => page.props.currentTeam);

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const themeOptions: {
    value: Appearance;
    label: string;
    description: string;
    icon: string;
}[] = [
    {
        value: 'light',
        label: 'Mode Terang',
        description: 'Skema terang untuk ruangan bercahaya',
        icon: 'light_mode',
    },
    {
        value: 'dark',
        label: 'Mode Gelap',
        description: 'Skema gelap untuk kerja malam',
        icon: 'dark_mode',
    },
    {
        value: 'system',
        label: 'Ikuti Sistem (Auto)',
        description: 'Mengikuti preferensi sistem operasi',
        icon: 'brightness_auto',
    },
];

function isActiveTheme(value: Appearance): boolean {
    // `mounted` guard: the stored preference lives in localStorage (ADR-19).
    return mounted.value && appearance.value === value;
}

const activeThemeLabel = computed(() => {
    if (!mounted.value) {
        return '';
    }

    return (
        themeOptions.find((option) => option.value === appearance.value)
            ?.label ?? ''
    );
});

function toggleQuickTheme(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

const systemRows = computed(() => [
    {
        label: 'Basis Data',
        value: props.system.database,
        detail: 'Eloquent + migration',
    },
    {
        label: 'Cache',
        value: props.system.cache,
        detail: 'Store cache aplikasi',
    },
    {
        label: 'Queue',
        value: props.system.queue,
        detail: 'Driver antrian pekerjaan',
    },
    {
        label: 'Session',
        value: props.system.session,
        detail: 'Penyimpanan sesi login',
    },
    {
        label: 'Mail',
        value: props.system.mail,
        detail: 'Pengiriman email transaksional',
    },
    {
        label: 'Versi',
        value: `Laravel ${props.system.laravel}`,
        detail: `PHP ${props.system.php}`,
    },
]);
</script>

<template>
    <Head title="Pengaturan" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="rounded-2xl border border-slate-100 bg-white p-5 shadow-xs transition-colors sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="mb-1 flex items-center gap-2">
                <h1
                    class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                >
                    Pengaturan Sistem &amp; Tema
                </h1>
                <span
                    class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-950 dark:text-blue-300"
                >
                    Preferensi
                </span>
            </div>
            <p class="text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                Sesuaikan tampilan antarmuka, tinjau profil akun operasional,
                dan periksa status sistem.
            </p>
        </div>

        <!-- Theme -->
        <div
            class="space-y-5 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs transition-colors dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-100 pb-4 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span
                        class="material-symbols-outlined text-[22px] text-blue-600 dark:text-blue-400"
                        >palette</span
                    >
                    <div>
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            Pilihan Tema Tampilan
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Preferensi disimpan di browser dan cookie, jadi ikut
                            terbaca saat halaman dirender di server.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <button
                    v-for="option in themeOptions"
                    :key="option.value"
                    type="button"
                    data-test="theme-option"
                    :data-theme="option.value"
                    :aria-pressed="isActiveTheme(option.value)"
                    :class="[
                        'flex flex-col justify-between rounded-2xl border-2 p-4 text-left transition-all',
                        isActiveTheme(option.value)
                            ? 'border-blue-600 bg-blue-50/50 shadow-md ring-2 ring-blue-500/20 dark:border-blue-500 dark:bg-blue-950/30'
                            : 'border-slate-200 bg-slate-50/70 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800/40 dark:hover:border-slate-600',
                    ]"
                    @click="updateAppearance(option.value)"
                >
                    <div
                        class="mb-3 h-24 w-full overflow-hidden rounded-xl border border-slate-200 bg-[#faf8ff] p-2.5 shadow-xs dark:border-slate-700 dark:bg-slate-950"
                    >
                        <div
                            class="mb-2 flex items-center justify-between border-b border-slate-200 pb-1.5 dark:border-slate-800"
                        >
                            <span
                                class="h-1.5 w-8 rounded bg-slate-300 dark:bg-slate-700"
                            ></span>
                            <span
                                class="h-1.5 w-3 rounded bg-slate-200 dark:bg-slate-700"
                            ></span>
                        </div>
                        <div
                            class="h-6 rounded border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                        ></div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span
                                class="material-symbols-outlined text-[20px] text-slate-500 dark:text-slate-300"
                            >
                                {{ option.icon }}
                            </span>
                            <div>
                                <h3
                                    class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ option.label }}
                                </h3>
                                <p
                                    class="text-[11px] text-slate-500 dark:text-slate-400"
                                >
                                    {{ option.description }}
                                </p>
                            </div>
                        </div>
                        <span
                            v-if="isActiveTheme(option.value)"
                            class="material-symbols-outlined text-[20px] font-bold text-blue-600 dark:text-blue-400"
                        >
                            check_circle
                        </span>
                    </div>
                </button>
            </div>

            <div
                v-if="mounted"
                class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs dark:border-slate-700 dark:bg-slate-800/60"
            >
                <div
                    class="flex items-center gap-2 text-slate-600 dark:text-slate-300"
                >
                    <span
                        class="material-symbols-outlined text-[18px] text-blue-600 dark:text-blue-400"
                        >info</span
                    >
                    <span>
                        Tema aktif saat ini:
                        <strong>{{ activeThemeLabel }}</strong>
                        <template v-if="appearance === 'system'">
                            ({{
                                resolvedAppearance === 'dark'
                                    ? 'Gelap'
                                    : 'Terang'
                            }}
                            mengikuti sistem)
                        </template>
                    </span>
                </div>
                <button
                    type="button"
                    class="font-bold text-blue-600 hover:underline dark:text-blue-400"
                    @click="toggleQuickTheme"
                >
                    Beralih Cepat ke
                    {{ resolvedAppearance === 'dark' ? 'Terang' : 'Gelap' }}
                </button>
            </div>
        </div>

        <!-- Account & RBAC -->
        <div
            class="space-y-5 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs transition-colors dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-100 pb-4 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span
                        class="material-symbols-outlined text-[22px] text-emerald-600 dark:text-emerald-400"
                        >badge</span
                    >
                    <div>
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            Profil Akun &amp; Hak Akses
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Persona pengguna yang sedang aktif di sesi ini.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <span
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#1e40af] text-lg font-bold text-white ring-2 ring-blue-500/30"
                >
                    {{ initials }}
                </span>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ user?.name }}
                        </h3>
                        <span
                            class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800 uppercase dark:bg-blue-900/60 dark:text-blue-300"
                        >
                            {{ team?.roleLabel ?? 'Tanpa Tim' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ user?.email }}
                    </p>
                    <p
                        class="mt-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Jabatan: {{ user?.job_title ?? 'Belum diisi' }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Tim aktif: {{ team?.name ?? '-' }}
                    </p>
                </div>
            </div>

            <div
                class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 text-xs sm:grid-cols-3 dark:border-slate-800"
            >
                <Link
                    :href="profileEdit()"
                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5 font-semibold text-slate-700 transition-colors hover:border-blue-200 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >person</span
                    >
                    <span>Ubah Profil</span>
                </Link>
                <Link
                    :href="securityEdit()"
                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5 font-semibold text-slate-700 transition-colors hover:border-blue-200 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >lock</span
                    >
                    <span>Keamanan &amp; Passkey</span>
                </Link>
                <Link
                    :href="teamIndex.url({ current_team: teamSlug })"
                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5 font-semibold text-slate-700 transition-colors hover:border-blue-200 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >group</span
                    >
                    <span>Kelola Tim</span>
                </Link>
            </div>
        </div>

        <!-- System status (replaces the template's Firestore panel) -->
        <div
            class="space-y-5 rounded-2xl border border-slate-100 bg-white p-6 shadow-xs transition-colors dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="border-b border-slate-100 pb-4 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span
                        class="material-symbols-outlined text-[22px] text-indigo-600 dark:text-indigo-400"
                    >
                        cloud_done
                    </span>
                    <div>
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            Status Sistem
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Konfigurasi backend yang sedang dipakai aplikasi
                            ini.
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="row in systemRows"
                    :key="row.label"
                    class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <span
                        class="text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500"
                    >
                        {{ row.label }}
                    </span>
                    <div
                        class="flex items-center gap-2 font-semibold text-slate-800 dark:text-slate-200"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-emerald-500"
                        ></span>
                        <span class="capitalize">{{ row.value }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ row.detail }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
