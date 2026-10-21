import { pageTitle, resolveLayout } from '@/app-config';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeFlashToast } from '@/lib/flashToast';
import { createInertiaApp } from '@inertiajs/vue3';

void createInertiaApp({
    title: pageTitle,
    layout: resolveLayout,
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
