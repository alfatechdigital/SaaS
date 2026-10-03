<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        title: string;
        subtitle?: string;
        maxWidth?: string;
    }>(),
    {
        maxWidth: 'max-w-2xl',
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

/**
 * `Teleport` cannot be server-rendered, so the overlay is only mounted on the
 * client. Rendering it during SSR produced a hydration node mismatch.
 */
const mounted = ref(false);

onMounted(() => {
    mounted.value = true;
});

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.isOpen) {
        emit('close');
    }
};

watch(
    () => props.isOpen,
    (val) => {
        // Guard for SSR: `document`/`window` do not exist in the Node renderer.
        if (typeof document === 'undefined') {
            return;
        }

        if (val) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', handleKeyDown);
        } else {
            document.body.style.overflow = 'unset';
            window.removeEventListener('keydown', handleKeyDown);
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.style.overflow = 'unset';
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <div
            v-if="isOpen"
            class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 duration-200 sm:p-6"
        >
            <div
                class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"
                @click="emit('close')"
            />
            <div
                :class="[
                    'animate-in zoom-in-95 relative z-10 w-full overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl transition-colors duration-200 sm:p-7 dark:border-slate-800 dark:bg-slate-900',
                    maxWidth,
                ]"
            >
                <div
                    class="flex items-start justify-between border-b border-slate-100 pb-4 dark:border-slate-800"
                >
                    <div>
                        <h3
                            class="text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100"
                        >
                            {{ title }}
                        </h3>
                        <p
                            v-if="subtitle"
                            class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ subtitle }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div
                    class="mt-4 max-h-[78vh] overflow-y-auto pr-1 text-slate-800 dark:text-slate-200"
                >
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
