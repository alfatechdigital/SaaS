import AdminLayout from '@/layouts/AdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Page title shared by the browser entry and the SSR entry, so both produce
 * the same `<title>`.
 */
export const pageTitle = (title?: string): string =>
    title ? `${title} - ${appName}` : appName;

/**
 * Layout for a page name, shared by the browser entry and the SSR entry.
 */
export function resolveLayout(name: string) {
    switch (true) {
        case name === 'Welcome':
            return null;
        // The public company profile is a standalone marketing page.
        case name.startsWith('public/'):
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('admin/'):
            return AdminLayout;
        // The platform layer is cross-tenant, so it must not use the tenant
        // shell (PDR-04).
        case name.startsWith('platform/'):
            return PlatformLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
}
