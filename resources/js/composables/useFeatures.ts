import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Reads the current team's entitlement (what the plan allows) shared by the server.
 *
 * Deliberately separate from `usePermission()`, which answers *who* the user is:
 * a feature gate says "does this plan include lead management?", while a
 * permission gate says "may this member manage leads?". Hiding a menu needs both,
 * and the server still rejects either failure on its own.
 *
 * @see docs/implementation/phase-04-plan-dan-entitlement.md §4.4
 */
export function useFeatures() {
    const page = usePage();

    const features = computed<string[]>(() => page.props.features ?? []);
    const limits = computed<Record<string, number>>(
        () => page.props.limits ?? {},
    );

    /**
     * Determine whether the current plan includes the given feature.
     */
    const can = (feature: string): boolean => features.value.includes(feature);

    /**
     * The plan's numeric limit for a metric, or `null` when it is undefined.
     */
    const limit = (metric: string): number | null =>
        limits.value[metric] ?? null;

    return { features, limits, can, limit };
}
