<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, LogOut } from '@lucide/vue';
import { computed } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { logout } from '@/routes';
import { index as tenantsIndex } from '@/routes/platform/tenants';

const page = usePage();

const user = computed(() => page.props.auth.user);
</script>

<template>
    <div
        class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100"
    >
        <header
            class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-[#1e40af] to-[#006398] text-white"
                    >
                        <Building2 class="h-5 w-5" />
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <span class="text-sm leading-tight font-bold">
                            Panel Platform
                        </span>
                        <span
                            class="truncate text-xs text-slate-500 dark:text-slate-400"
                        >
                            Lintas tenant — tidak terikat satu perusahaan
                        </span>
                    </div>
                </div>

                <nav class="flex items-center gap-1">
                    <Link
                        :href="tenantsIndex()"
                        class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        Tenant
                    </Link>
                </nav>

                <div class="flex items-center gap-3">
                    <span
                        class="hidden text-sm text-slate-500 sm:block dark:text-slate-400"
                    >
                        {{ user?.name }}
                    </span>
                    <Link
                        :href="logout()"
                        method="post"
                        as="button"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <LogOut class="h-4 w-4" />
                        Keluar
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <slot />
        </main>

        <Toaster />
    </div>
</template>
