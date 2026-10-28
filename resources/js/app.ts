import { pageTitle, resolveLayout } from '@/app-config';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeFlashToast } from '@/lib/flashToast';
import { createInertiaApp } from '@inertiajs/vue3';

void createInertiaApp({
    title: pageTitle,
    layout: resolveLayout,
    // Raw `<head>` markup supplied by the server (the JSON-LD block). A Vue
    // template cannot contain a `<script>` — the compiler rejects it — so the
    // controller sends finished HTML and Inertia merges it with the `<Head>`
    // output of the page. See `App\Support\PublicPageSeo::head()`.
    serverHead: 'seoHead',
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
