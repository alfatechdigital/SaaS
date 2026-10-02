import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { TeamPermissions } from '@/types';

export type PermissionKey = keyof TeamPermissions;

/**
 * Reads the current team's permission flags shared by the server.
 *
 * Replaces the template's `canAccess(module)` helper, which hardcoded a role →
 * module mapping in the browser. Here the server decides, so the UI can never
 * show a menu the backend would reject.
 *
 * @see docs/IMPLEMENTATION_PLAN.md ADR-09
 */
export function usePermission() {
    const page = usePage();

    const permissions = computed<TeamPermissions | null>(
        () => page.props.teamPermissions ?? null,
    );

    /**
     * Determine whether the current user holds the given permission.
     */
    const can = (permission: PermissionKey): boolean =>
        permissions.value?.[permission] ?? false;

    /**
     * Determine whether the current user holds at least one of the permissions.
     */
    const canAny = (...keys: PermissionKey[]): boolean => keys.some(can);

    return { permissions, can, canAny };
}
