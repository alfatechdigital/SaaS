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
  { label: 'Dashboard', href: dashboard({ current_team: teamSlug.value }).url },
  { label: 'Proyek', href: projectsIndex.url({ current_team: teamSlug.value }) },
  { label: 'Leads / CRM', href: leadsIndex.url({ current_team: teamSlug.value }) },
  { label: 'Profil Perusahaan', href: companyProfileEdit.url({ current_team: teamSlug.value }) },
  { label: 'Portofolio', href: portfolioIndex.url({ current_team: teamSlug.value }) },
  { label: 'Konten Medsos', href: contentsIndex.url({ current_team: teamSlug.value }) },
  { label: 'Keuangan', href: transactionsIndex.url({ current_team: teamSlug.value }) },
  { label: 'Log Aktivitas', href: activityLogsIndex.url({ current_team: teamSlug.value }) },
  { label: 'Kelola Tim', href: teamIndex.url({ current_team: teamSlug.value }) },
  { label: 'Profil Saya', href: profileEdit().url },
]);

const searchResults = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();

  if (!query) {
    return [];
  }

  return modules.value.filter((module) => module.label.toLowerCase().includes(query)).slice(0, 6);
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
  <header class="sticky top-0 right-0 left-0 h-16 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border-b border-slate-200/70 dark:border-slate-800 shadow-xs z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 transition-colors duration-200">
    <!-- Left items: Mobile toggle, breadcrumb, global search -->
    <div class="flex items-center gap-3 sm:gap-4 flex-1">
      <button
        @click="emit('toggle-sidebar')"
        class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
        aria-label="Buka Menu"
      >
        <span class="material-symbols-outlined text-[24px]">menu</span>
      </button>

      <div class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-medium">
        <span class="material-symbols-outlined text-[18px]">hub</span>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <!-- The tenant name, not a hardcoded one: this shell serves every tenant. -->
        <span
          data-test="topnav-breadcrumb-team"
          class="text-slate-900 dark:text-slate-200 font-semibold truncate"
        >
          {{ team?.name ?? 'Tanpa Tim' }}
        </span>
        <span class="text-slate-300 dark:text-slate-600 hidden sm:inline">/</span>
        <span
          data-test="topnav-breadcrumb-module"
          class="text-blue-700 dark:text-blue-400 font-bold hidden sm:inline truncate capitalize"
        >
          {{ moduleName }}
        </span>
      </div>

      <!-- Global module quick-nav -->
      <div class="relative ml-2 hidden w-72 md:block lg:w-80">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-slate-400 dark:text-slate-500">
          search
        </span>
        <input
          v-model="searchQuery"
          class="w-full h-9 pl-9 pr-3 rounded-lg bg-slate-100/80 dark:bg-slate-800/90 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 text-xs sm:text-sm focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-blue-600 dark:focus:ring-blue-500 border border-transparent dark:border-slate-700/50 transition-all"
          placeholder="Cari menu atau modul..."
          type="text"
          data-test="global-search"
          @keydown.enter.prevent="goToFirstResult"
          @keydown.esc="searchQuery = ''"
        />

        <div
          v-if="searchResults.length > 0"
          class="absolute left-0 right-0 top-11 z-40 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
        >
          <button
            v-for="result in searchResults"
            :key="result.href"
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700"
            data-test="global-search-result"
            @click="goTo(result.href)"
          >
            <span class="material-symbols-outlined text-[16px] text-slate-400">chevron_right</span>
            <span>{{ result.label }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Right items: Tambah Cepat, Theme Toggle, Notifikasi, User Info -->
    <div class="flex items-center gap-2 sm:gap-3">
      <button
        @click="emit('open-quick-add')"
        class="flex items-center gap-1.5 bg-[#1e40af] hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold px-3 sm:px-4 py-2 rounded-lg shadow-sm transition-all"
        type="button"
      >
        <span class="material-symbols-outlined text-[18px]">add</span>
        <span class="hidden xs:inline">Tambah Cepat</span>
      </button>

      <!-- Theme toggle -->
      <button
        @click="toggleTheme"
        aria-label="Ubah tema"
        class="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition-colors border border-transparent dark:border-slate-800/80"
        type="button"
      >
        <span class="dark:hidden">
          <span class="material-symbols-outlined text-[22px]">dark_mode</span>
        </span>
        <span class="hidden dark:inline">
          <span class="material-symbols-outlined text-[22px]">light_mode</span>
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
                class="w-8 h-8 rounded-full object-cover ring-2 ring-slate-100 dark:ring-slate-700"
                :src="user.avatar"
              />
              <span
                v-else
                class="w-8 h-8 rounded-full ring-2 ring-slate-100 dark:ring-slate-700 bg-[#1e40af] text-white text-[11px] font-bold flex items-center justify-center"
              >
                {{ initials }}
              </span>
              <span class="absolute bottom-0 right-0 w-2 h-2 bg-emerald-500 rounded-full ring-2 ring-white dark:ring-slate-900"></span>
            </div>
            <div class="hidden xl:block text-left">
              <div class="text-xs text-slate-900 dark:text-slate-100 font-bold leading-tight truncate">
                {{ user?.name }}
              </div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight truncate uppercase">
                {{ team?.roleLabel ?? 'Tanpa Tim' }} &bull; {{ user?.job_title ?? team?.name ?? 'Staff' }}
              </div>
            </div>
            <span class="material-symbols-outlined text-[18px] text-slate-400">expand_more</span>
          </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-56" data-test="topnav-user-menu-content">
          <UserMenuPanel :user="user" :team="team" />
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </header>
</template>
