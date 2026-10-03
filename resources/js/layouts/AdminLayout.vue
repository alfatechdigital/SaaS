<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/components/Sidebar.vue';
import TopNavbar from '@/components/TopNavbar.vue';
import { Toaster } from '@/components/ui/sonner';

/**
 * Shell for the ported Alfatech admin pages (`resources/js/pages/admin/**`).
 *
 * The starter kit layouts stay in place for `teams/` and `settings/`.
 *
 * `<Toaster />` must be mounted here: `lib/flashToast.ts` listens for Inertia's
 * `flash` event, but without a Toaster in the tree the messages were silently
 * dropped on every admin page. The starter kit layouts already mount their own.
 *
 * @see docs/IMPLEMENTATION_PLAN.md ADR-16
 */
const page = usePage();

const mobileSidebarOpen = ref(false);

const MODULE_NAMES: Array<[prefix: string, label: string]> = [
    ['/settings', 'Pengaturan'],
    ['/team', 'Tim'],
    ['/dashboard', 'Dashboard'],
    ['/projects', 'Proyek'],
    ['/leads', 'Leads / CRM'],
    ['/company-profile', 'Profil Perusahaan'],
    ['/portfolio', 'Portofolio'],
    ['/contents', 'Konten Medsos'],
    ['/transactions', 'Keuangan'],
    ['/activity-logs', 'Log Aktivitas'],
];

const moduleName = computed<string>(() => {
    const url = page.url.split('?')[0];
    const match = MODULE_NAMES.find(([prefix]) => url.includes(prefix));

    if (match) {
        return match[1];
    }

    // Deliberately NOT falling back to "Dashboard": an unmapped page silently
    // claiming to be the Dashboard is exactly how the `/team` page ended up
    // mislabelled. The last URL segment is at worst generic, but never wrong.
    const segment = url.split('/').filter(Boolean).pop();

    return segment ? segment.replaceAll('-', ' ') : 'Dashboard';
});
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 dark:bg-slate-950">
        <Sidebar
            :mobile-open="mobileSidebarOpen"
            @close="mobileSidebarOpen = false"
        />

        <div class="flex min-w-0 flex-1 flex-col">
            <TopNavbar
                :module-name="moduleName"
                @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen"
            />

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <Toaster />
    </div>
</template>
