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
  }
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
  { immediate: true }
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
      class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200"
    >
      <div
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"
        @click="emit('close')"
      />
      <div
        :class="[
          'relative w-full bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-800 p-6 sm:p-7 overflow-hidden z-10 animate-in zoom-in-95 duration-200 transition-colors',
          maxWidth
        ]"
      >
        <div class="flex items-start justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">{{ title }}</h3>
            <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            @click="emit('close')"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="mt-4 max-h-[78vh] overflow-y-auto pr-1 text-slate-800 dark:text-slate-200">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
