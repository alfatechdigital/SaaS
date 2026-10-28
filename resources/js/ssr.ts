import { pageTitle, resolveLayout } from '@/app-config';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h, type DefineComponent } from 'vue';

/**
 * Entry point for the Inertia SSR bundle.
 *
 * `npm run build:ssr` compiles this file into `bootstrap/ssr`, which is then
 * served by `php artisan inertia:start-ssr` on 127.0.0.1:13714 (see
 * `config/inertia.php`). Only the Inertia app is set up here: browser-only work
 * such as `initializeTheme()` and flash toasts stays in `app.ts`.
 *
 * @see resources/js/app-config.ts — shared title/layout mapping
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: pageTitle,
        layout: resolveLayout,
        // Same server-supplied head markup as `app.ts`, so the SSR HTML and the
        // hydrated page carry the same head.
        serverHead: 'seoHead',
        resolve: (name) =>
            resolvePageComponent<DefineComponent>(
                `./pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./pages/**/*.vue'),
            ),
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin);
        },
    }),
);
