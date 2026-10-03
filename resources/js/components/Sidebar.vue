<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import UserMenuPanel from '@/components/UserMenuPanel.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { usePermission, type PermissionKey } from '@/composables/usePermission';
import { dashboard } from '@/routes';
import { index as activityLogsIndex } from '@/routes/activity-logs';
import { edit as companyProfileEdit } from '@/routes/company-profile';
import { index as contentsIndex } from '@/routes/contents';
import { index as teamIndex } from '@/routes/team';
import { index as leadsIndex } from '@/routes/leads';
import { index as portfolioIndex } from '@/routes/portfolio';
import { index as projectsIndex } from '@/routes/projects';
import { index as settingsIndex } from '@/routes/settings';
import { index as transactionsIndex } from '@/routes/transactions';

interface NavItem {
    label: string;
    icon: string;
    href: string;
    permission?: PermissionKey;
    exact?: boolean;
}

withDefaults(
    defineProps<{
        mobileOpen?: boolean;
    }>(),
    {
        mobileOpen: false,
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const page = usePage();
const { can } = usePermission();
const { updateAppearance } = useAppearance();

const user = computed(() => page.props.auth.user);
const team = computed(() => page.props.currentTeam);
const teamSlug = computed(() => team.value?.slug ?? '');
const initials = computed(() =>
    user.value.name
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const navItems = computed<NavItem[]>(() => [
    {
        label: 'Dashboard',
        icon: 'dashboard',
        href: dashboard.url({ current_team: teamSlug.value }),
        exact: true,
    },
    {
        label: 'Proyek',
        icon: 'folder_open',
        href: projectsIndex.url({ current_team: teamSlug.value }),
    },
    {
        label: 'Leads / CRM',
        icon: 'group',
        href: leadsIndex.url({ current_team: teamSlug.value }),
        permission: 'canManageLeads',
    },
    {
        label: 'Profil Perusahaan',
        icon: 'apartment',
        href: companyProfileEdit.url({ current_team: teamSlug.value }),
        permission: 'canManageCompanyProfile',
    },
    {
        label: 'Portofolio',
        icon: 'work',
        href: portfolioIndex.url({ current_team: teamSlug.value }),
        permission: 'canManagePortfolio',
    },
    {
        label: 'Konten Medsos',
        icon: 'share',
        href: contentsIndex.url({ current_team: teamSlug.value }),
        permission: 'canManageContent',
    },
    {
        label: 'Keuangan',
        icon: 'account_balance_wallet',
        href: transactionsIndex.url({ current_team: teamSlug.value }),
        permission: 'canManageFinance',
    },
    {
        label: 'Log Aktivitas',
        icon: 'history',
        href: activityLogsIndex.url({ current_team: teamSlug.value }),
        permission: 'canViewActivityLog',
    },
    {
        label: 'Tim',
        icon: 'how_to_reg',
        href: teamIndex.url({ current_team: teamSlug.value }),
        permission: 'canCreateInvitation',
    },
    {
        label: 'Pengaturan',
        icon: 'settings',
        href: settingsIndex.url({ current_team: teamSlug.value }),
    },
]);

function canAccess(item: NavItem): boolean {
    return item.permission ? can(item.permission) : true;
}

function isActive(item: NavItem): boolean {
    const path = item.href.split('?')[0];

    return item.exact
        ? page.url === path
        : page.url === path || page.url.startsWith(`${path}/`);
}

function toggleTheme(): void {
    // Read the applied theme from the DOM so this stays SSR-safe: the `dark`
    // class is set by the inline script in app.blade.php before hydration.
    const isDark = document.documentElement.classList.contains('dark');

    updateAppearance(isDark ? 'light' : 'dark');
}
</script>

<template>
    <div
        v-if="mobileOpen"
        class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden"
        @click="emit('close')"
    />

    <aside
        :class="[
            'fixed inset-y-0 left-0 z-50 flex h-screen w-72 flex-col border-r border-white/5 bg-[#1e2538] text-[#eef0ff] shadow-xl transition-transform duration-300 lg:sticky lg:top-0 lg:translate-x-0 dark:border-slate-800 dark:bg-slate-950',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <!-- Brand Header -->
        <div
            class="flex h-16 items-center gap-3 border-b border-white/5 bg-[#1e2538] px-5 dark:border-slate-800/80 dark:bg-slate-950"
        >
            <div
                class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-[#1e40af] to-[#006398] text-white shadow-md"
            >
                <span class="material-symbols-outlined text-[22px]">hub</span>
            </div>
            <div class="flex min-w-0 flex-col">
                <span
                    class="truncate text-base leading-tight font-bold tracking-tight text-[#eef0ff]"
                >
                    Alfatech Office
                </span>
                <span
                    class="truncate text-[11px] font-medium text-[#b8c4ff] dark:text-slate-400"
                >
                    PT Alfatech Digital Solutions
                </span>
            </div>
        </div>

        <!-- Status Pill -->
        <div class="px-5 py-3">
            <div
                class="flex items-center justify-between rounded-lg border border-white/5 bg-white/5 px-3 py-1.5 text-xs font-semibold text-[#eef0ff] dark:border-slate-800 dark:bg-slate-900/80"
            >
                <span
                    class="text-[10px] tracking-wider text-[#dde1ff] uppercase dark:text-slate-400"
                >
                    OPERATIONAL PORTAL
                </span>
                <span class="relative flex h-2 w-2">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#3fd298] opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full bg-[#3fd298]"
                    ></span>
                </span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-1">
            <template v-for="item in navItems" :key="item.label">
                <Link
                    v-if="canAccess(item)"
                    :href="item.href"
                    :class="[
                        'group flex w-full items-center justify-between rounded-xl px-3.5 py-2.5 text-left transition-all',
                        isActive(item)
                            ? 'bg-[#1e40af] font-bold text-white shadow-md shadow-[#1e40af]/30 dark:bg-blue-600'
                            : 'font-medium text-[#eef0ff]/80 hover:bg-white/10 hover:text-white dark:text-slate-300 dark:hover:bg-slate-900',
                    ]"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            :class="[
                                'material-symbols-outlined flex-shrink-0 text-[20px]',
                                isActive(item)
                                    ? 'text-white'
                                    : 'text-[#eef0ff]/70 group-hover:text-white dark:text-slate-400',
                            ]"
                        >
                            {{ item.icon }}
                        </span>
                        <span
                            class="truncate text-xs tracking-normal sm:text-sm"
                            >{{ item.label }}</span
                        >
                    </div>
                </Link>

                <div
                    v-else
                    :title="`Akses dibatasi untuk role ${team?.roleLabel ?? ''}`"
                    class="flex w-full cursor-not-allowed items-center justify-between rounded-xl px-3.5 py-2.5 text-[#eef0ff]/30 dark:text-slate-600"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="material-symbols-outlined flex-shrink-0 text-[20px]"
                            >{{ item.icon }}</span
                        >
                        <span
                            class="truncate text-xs tracking-normal sm:text-sm"
                            >{{ item.label }}</span
                        >
                    </div>
                    <span
                        class="material-symbols-outlined text-[15px] text-white/30"
                        >lock</span
                    >
                </div>
            </template>
        </nav>

        <!-- Theme Toggle + User Card -->
        <div
            class="relative border-t border-white/10 bg-[#1e2538] p-3.5 dark:border-slate-800 dark:bg-slate-950"
        >
            <button
                type="button"
                @click="toggleTheme"
                class="mb-2 flex w-full items-center justify-center gap-2 rounded-lg border border-white/5 bg-white/5 px-3 py-2 transition-colors hover:bg-white/10 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800"
            >
                <span class="dark:hidden">
                    <span
                        class="material-symbols-outlined text-[18px] text-[#dde1ff] dark:text-slate-300"
                        >dark_mode</span
                    >
                </span>
                <span class="hidden dark:inline">
                    <span
                        class="material-symbols-outlined text-[18px] text-[#dde1ff] dark:text-slate-300"
                        >light_mode</span
                    >
                </span>
                <span
                    class="text-[11px] font-semibold text-[#dde1ff] dark:hidden dark:text-slate-300"
                    >Mode Gelap</span
                >
                <span
                    class="hidden text-[11px] font-semibold text-[#dde1ff] dark:inline dark:text-slate-300"
                    >Mode Terang</span
                >
            </button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-test="sidebar-user-menu"
                        class="group flex w-full items-center gap-3 rounded-xl border border-white/5 bg-white/5 p-2.5 text-left transition-colors hover:bg-white/10 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/80"
                    >
                        <div class="relative flex-shrink-0">
                            <img
                                v-if="user.avatar"
                                :alt="user.name"
                                class="h-9 w-9 rounded-full object-cover ring-2 ring-white/10 transition-all group-hover:ring-[#1e40af]"
                                :src="user.avatar"
                            />
                            <span
                                v-else
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-[#1e40af] text-xs font-bold text-white ring-2 ring-white/10 transition-all group-hover:ring-[#1e40af]"
                            >
                                {{ initials }}
                            </span>
                            <span
                                class="absolute right-0 bottom-0 h-2.5 w-2.5 rounded-full bg-[#3fd298] ring-2 ring-[#1e2538]"
                            ></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-xs leading-tight font-bold text-[#eef0ff]"
                            >
                                {{ user.name }}
                            </p>
                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span
                                    class="py-0.2 rounded bg-white/15 px-1.5 text-[10px] font-bold text-[#dde1ff] uppercase dark:bg-slate-800"
                                >
                                    {{ team?.roleLabel ?? 'Tanpa Tim' }}
                                </span>
                                <span
                                    class="truncate text-[11px] font-normal text-[#b8c4ff] dark:text-slate-400"
                                >
                                    {{ user.job_title ?? team?.name }}
                                </span>
                            </div>
                        </div>
                        <span
                            class="material-symbols-outlined text-[18px] text-[#b8c4ff] dark:text-slate-400"
                        >
                            expand_less
                        </span>
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    align="start"
                    class="w-56"
                    data-test="sidebar-user-menu-content"
                >
                    <UserMenuPanel :user="user" :team="team" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </aside>
</template>
