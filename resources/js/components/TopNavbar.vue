<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserMenuPanel from '@/components/UserMenuPanel.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard } from '@/routes';
import { index as activityLogsIndex } from '@/routes/activity-logs';
import { edit as companyProfileEdit } from '@/routes/company-profile';
import { index as contentsIndex } from '@/routes/contents';
import { index as leadsIndex } from '@/routes/leads';
import { index as portfolioIndex } from '@/routes/portfolio';
import { index as projectsIndex } from '@/routes/projects';
import { edit as profileEdit } from '@/routes/profile';
import { index as teamIndex } from '@/routes/team';
import { index as transactionsIndex } from '@/routes/transactions';

withDefaults(
    defineProps<{
        moduleName?: string;
    }>(),
    {
        moduleName: 'Dashboard',
    },
);

const emit = defineEmits<{
    (e: 'open-quick-add'): void;
    (e: 'toggle-sidebar'): void;
}>();

const page = usePage();
const { updateAppearance } = useAppearance();

const searchQuery = ref('');

const user = computed(() => page.props.auth.user);
const team = computed(() => page.props.currentTeam);
const teamSlug = computed(() => team.value?.slug ?? '');

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

/**
 * Module quick-nav. The input previously stored state and did nothing, and its
 * placeholder promised "cari proyek, klien, atau leads" — a search that needs a
 * backend endpoint this app does not have. It now navigates between the pages
 * that actually exist, which is what the box can honestly deliver.
 */
const modules = computed(() => [
    {
        label: 'Dashboard',
        href: dashboard({ current_team: teamSlug.value }).url,
    },
    {
        label: 'Proyek',
        href: projectsIndex.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Leads / CRM',
        href: leadsIndex.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Profil Perusahaan',
        href: companyProfileEdit.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Portofolio',
        href: portfolioIndex.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Konten Medsos',
        href: contentsIndex.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Keuangan',
        href: transactionsIndex.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Log Aktivitas',
        href: activityLogsIndex.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Kelola Tim',
        href: teamIndex.url({ current_team: teamSlug.value }),
    },
    { label: 'Profil Saya', href: profileEdit().url },
]);

const searchResults = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return [];
    }

    return modules.value
        .filter((module) => module.label.toLowerCase().includes(query))
        .slice(0, 6);
});

function goToFirstResult(): void {
    const first = searchResults.value[0];

    if (!first) {
        return;
    }

    searchQuery.value = '';
    router.visit(first.href);
}

function goTo(href: string): void {
    searchQuery.value = '';
    router.visit(href);
}

function toggleTheme(): void {
    // Read the applied theme from the DOM so this stays SSR-safe: the `dark`
    // class is set by the inline script in app.blade.php before hydration.
    const isDark = document.documentElement.classList.contains('dark');

    updateAppearance(isDark ? 'light' : 'dark');
}
</script>

<template>
    <header
        class="sticky top-0 right-0 left-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/70 bg-white/90 px-4 shadow-xs backdrop-blur-xl transition-colors duration-200 sm:px-6 lg:px-8 dark:border-slate-800 dark:bg-slate-900/90"
    >
        <!-- Left items: Mobile toggle, breadcrumb, global search -->
        <div class="flex flex-1 items-center gap-3 sm:gap-4">
            <button
                @click="emit('toggle-sidebar')"
                class="rounded-lg p-2 text-slate-600 transition-colors hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800"
                aria-label="Buka Menu"
            >
                <span class="material-symbols-outlined text-[24px]">menu</span>
            </button>

            <div
                class="flex items-center gap-1.5 text-xs font-medium text-slate-500 sm:text-sm dark:text-slate-400"
            >
                <span class="material-symbols-outlined text-[18px]">hub</span>
                <span class="text-slate-300 dark:text-slate-600">/</span>
                <!-- The tenant name, not a hardcoded one: this shell serves every tenant. -->
                <span
                    data-test="topnav-breadcrumb-team"
                    class="truncate font-semibold text-slate-900 dark:text-slate-200"
                >
                    {{ team?.name ?? 'Tanpa Tim' }}
                </span>
                <span
                    class="hidden text-slate-300 sm:inline dark:text-slate-600"
                    >/</span
                >
                <span
                    data-test="topnav-breadcrumb-module"
                    class="hidden truncate font-bold text-blue-700 capitalize sm:inline dark:text-blue-400"
                >
                    {{ moduleName }}
                </span>
            </div>

            <!-- Global module quick-nav -->
            <div class="relative ml-2 hidden w-72 md:block lg:w-80">
                <span
                    class="material-symbols-outlined absolute top-2.5 left-3 text-[18px] text-slate-400 dark:text-slate-500"
                >
                    search
                </span>
                <input
                    v-model="searchQuery"
                    class="h-9 w-full rounded-lg border border-transparent bg-slate-100/80 pr-3 pl-9 text-xs text-slate-900 transition-all placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-blue-600 focus:outline-none sm:text-sm dark:border-slate-700/50 dark:bg-slate-800/90 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:bg-slate-800 dark:focus:ring-blue-500"
                    placeholder="Cari menu atau modul..."
                    type="text"
                    data-test="global-search"
                    @keydown.enter.prevent="goToFirstResult"
                    @keydown.esc="searchQuery = ''"
                />

                <div
                    v-if="searchResults.length > 0"
                    class="absolute top-11 right-0 left-0 z-40 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
                >
                    <button
                        v-for="result in searchResults"
                        :key="result.href"
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700"
                        data-test="global-search-result"
                        @click="goTo(result.href)"
                    >
                        <span
                            class="material-symbols-outlined text-[16px] text-slate-400"
                            >chevron_right</span
                        >
                        <span>{{ result.label }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right items: Tambah Cepat, Theme Toggle, Notifikasi, User Info -->
        <div class="flex items-center gap-2 sm:gap-3">
            <button
                @click="emit('open-quick-add')"
                class="flex items-center gap-1.5 rounded-lg bg-[#1e40af] px-3 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-700 sm:px-4 sm:text-sm dark:bg-blue-600 dark:hover:bg-blue-500"
                type="button"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span class="xs:inline hidden">Tambah Cepat</span>
            </button>

            <!-- Theme toggle -->
            <button
                @click="toggleTheme"
                aria-label="Ubah tema"
                class="rounded-lg border border-transparent p-2 text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:border-slate-800/80 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                type="button"
            >
                <span class="dark:hidden">
                    <span class="material-symbols-outlined text-[22px]"
                        >dark_mode</span
                    >
                </span>
                <span class="hidden dark:inline">
                    <span class="material-symbols-outlined text-[22px]"
                        >light_mode</span
                    >
                </span>
            </button>

            <!-- User menu -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-test="topnav-user-menu"
                        class="flex items-center gap-2.5 border-l border-slate-200 pl-1 transition-opacity hover:opacity-90 sm:pl-2 dark:border-slate-700"
                    >
                        <div class="relative">
                            <img
                                v-if="user?.avatar"
                                :alt="user.name"
                                class="h-8 w-8 rounded-full object-cover ring-2 ring-slate-100 dark:ring-slate-700"
                                :src="user.avatar"
                            />
                            <span
                                v-else
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-[#1e40af] text-[11px] font-bold text-white ring-2 ring-slate-100 dark:ring-slate-700"
                            >
                                {{ initials }}
                            </span>
                            <span
                                class="absolute right-0 bottom-0 h-2 w-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900"
                            ></span>
                        </div>
                        <div class="hidden text-left xl:block">
                            <div
                                class="truncate text-xs leading-tight font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ user?.name }}
                            </div>
                            <div
                                class="truncate text-[11px] leading-tight text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ team?.roleLabel ?? 'Tanpa Tim' }} &bull;
                                {{ user?.job_title ?? team?.name ?? 'Staff' }}
                            </div>
                        </div>
                        <span
                            class="material-symbols-outlined text-[18px] text-slate-400"
                            >expand_more</span
                        >
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    align="end"
                    class="w-56"
                    data-test="topnav-user-menu-content"
                >
                    <UserMenuPanel :user="user" :team="team" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
