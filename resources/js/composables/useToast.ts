import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types';

export type ToastType = FlashToast['type'];

/**
 * Client-side toast helper.
 *
 * Server-driven toasts already arrive through `lib/flashToast.ts` whenever a
 * controller calls `Inertia::flash('toast', [...])`. This composable covers the
 * cases where a component needs to raise a toast without a round trip, using
 * the same `(message, type, title)` signature as the React template.
 */
export function useToast() {
    /**
     * Show a toast notification.
     */
    function showToast(message: string, type: ToastType = 'success', title?: string): void {
        const content = title ?? message;
        const options = title ? { description: message } : undefined;

        switch (type) {
            case 'error':
                toast.error(content, options);
                break;
            case 'warning':
                toast.warning(content, options);
                break;
            case 'info':
                toast.info(content, options);
                break;
            default:
                toast.success(content, options);
        }
    }

    return { showToast, toast };
}
