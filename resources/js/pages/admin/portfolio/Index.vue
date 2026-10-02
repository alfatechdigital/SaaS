<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import {
    destroy as portfolioDestroy,
    store as portfolioStore,
    update as portfolioUpdate,
} from '@/routes/portfolio';
import type { PortfolioItem } from '@/types';

/**
 * Portfolio catalogue with full CRUD.
 *
 * Ported from `PortfolioPage.tsx`. The template's `technologies` textarea
 * (comma separated) is kept, and converted to an array on submit via
 * `form.transform` because the API expects `technologies: string[]`.
 */
const props = defineProps<{
    items: PortfolioItem[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const categoryFilter = ref('all');
const searchQuery = ref('');
const isModalOpen = ref(false);
const editingItem = ref<PortfolioItem | null>(null);

const categories = computed(() => [
    'all',
    ...Array.from(new Set(props.items.map((item) => item.category).filter((c): c is string => !!c))),
]);

const filteredItems = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.items.filter((item) => {
        if (categoryFilter.value !== 'all' && item.category !== categoryFilter.value) {
            return false;
        }

        if (!query) {
            return true;
        }

        return (
            item.title.toLowerCase().includes(query) ||
            (item.client ?? '').toLowerCase().includes(query) ||
            item.technologies.some((tech) => tech.toLowerCase().includes(query))
        );
    });
});

function today(): string {
    return new Date().toISOString().split('T')[0];
}

const form = useForm({
    title: '',
    client: '',
    category: 'Enterprise ERP',
    description: '',
    technologies: 'React, Node.js, Tailwind CSS',
    image_url: '',
    project_url: '',
    completion_date: today(),
    featured: false,
    published: true,
});

form.transform((data) => ({
    ...data,
    technologies: data.technologies
        .split(',')
        .map((tech) => tech.trim())
        .filter(Boolean),
}));

const inputClass =
    'w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none';
const textareaClass =
    'w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none leading-relaxed';
const labelClass = 'block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1';

function openCreateModal(): void {
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
}

function openEditModal(item: PortfolioItem): void {
    editingItem.value = item;

    form.title = item.title;
    form.client = item.client ?? '';
    form.category = item.category ?? '';
    form.description = item.description ?? '';
    form.technologies = item.technologies.join(', ');
    form.image_url = item.imageUrl ?? '';
    form.project_url = item.projectUrl ?? '';
    form.completion_date = item.completionDate ?? today();
    form.featured = item.featured;
    form.published = item.published;

    form.clearErrors();
    isModalOpen.value = true;
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isModalOpen.value = false;
            editingItem.value = null;
            form.reset();
        },
    };

    if (editingItem.value) {
        form.put(
            portfolioUpdate.url({
                current_team: teamSlug.value,
                portfolioItem: editingItem.value.id,
            }),
            options,
        );

        return;
    }

    form.post(portfolioStore.url({ current_team: teamSlug.value }), options);
}

function togglePublished(item: PortfolioItem): void {
    router.put(
        portfolioUpdate.url({ current_team: teamSlug.value, portfolioItem: item.id }),
        {
            title: item.title,
            client: item.client,
            category: item.category,
            description: item.description,
            technologies: item.technologies,
            image_url: item.imageUrl,
            project_url: item.projectUrl,
            completion_date: item.completionDate,
            featured: item.featured,
            published: !item.published,
        },
        { preserveScroll: true },
    );
}

function remove(item: PortfolioItem): void {
    if (!window.confirm(`Hapus portofolio "${item.title}"?`)) {
        return;
    }

    router.delete(
        portfolioDestroy.url({ current_team: teamSlug.value, portfolioItem: item.id }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Portofolio" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100">
                    Katalog Portofolio &amp; Case Studies
                </h1>
                <p class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                    Showcase proyek software pilihan untuk presentasi klien dan profil website publik.
                </p>
            </div>
            <button
                class="flex items-center gap-1.5 self-start rounded-xl bg-[#1e40af] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-700 sm:self-auto sm:text-sm"
                @click="openCreateModal"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Tambah Portofolio</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-semibold sm:text-sm">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    :class="[
                        'whitespace-nowrap rounded-lg px-3 py-1.5 transition-all',
                        categoryFilter === cat
                            ? 'bg-[#1e40af] text-white shadow-xs'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                    ]"
                    @click="categoryFilter = cat"
                >
                    {{ cat === 'all' ? 'Semua Kategori' : cat }}
                </button>
            </div>

            <div class="relative w-full sm:w-64">
                <span class="material-symbols-outlined absolute top-2.5 left-3 text-[18px] text-slate-400">search</span>
                <input
                    v-model="searchQuery"
                    placeholder="Cari portofolio..."
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                />
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="filteredItems.length === 0"
            class="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900"
        >
            <span class="material-symbols-outlined text-[40px] text-slate-300 dark:text-slate-600">work</span>
            <p class="mt-2 text-sm font-semibold text-slate-500 dark:text-slate-400">
                Belum ada portofolio yang cocok.
            </p>
        </div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="item in filteredItems"
                :key="item.id"
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
            >
                <div>
                    <div class="relative h-48 w-full overflow-hidden bg-slate-100 dark:bg-slate-800">
                        <img
                            v-if="item.imageUrl"
                            :src="item.imageUrl"
                            :alt="item.title"
                            class="h-full w-full object-cover transition-transform duration-500 hover:scale-105"
                        />
                        <div class="absolute top-3 left-3 flex gap-1.5">
                            <span
                                class="rounded-md bg-slate-900/80 px-2.5 py-1 text-[10px] font-bold text-white backdrop-blur-xs"
                            >
                                {{ item.category }}
                            </span>
                            <span
                                v-if="item.featured"
                                class="rounded-md bg-amber-500 px-2.5 py-1 text-[10px] font-bold text-white shadow-xs"
                            >
                                Featured
                            </span>
                        </div>
                        <div class="absolute top-3 right-3">
                            <button
                                :class="[
                                    'rounded-md px-2 py-1 text-[10px] font-bold shadow-xs',
                                    item.published ? 'bg-emerald-600 text-white' : 'bg-slate-700/80 text-slate-200',
                                ]"
                                @click="togglePublished(item)"
                            >
                                {{ item.published ? 'Live Publik' : 'Draf' }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3 p-5">
                        <div>
                            <span class="text-[11px] font-bold tracking-wide text-blue-700 uppercase dark:text-blue-400">
                                {{ item.client }}
                            </span>
                            <h3 class="mt-0.5 text-base leading-snug font-bold text-slate-900 dark:text-slate-100">
                                {{ item.title }}
                            </h3>
                        </div>

                        <p class="line-clamp-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                            {{ item.description }}
                        </p>

                        <div class="flex flex-wrap gap-1 pt-1">
                            <span
                                v-for="tech in item.technologies"
                                :key="tech"
                                class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ tech }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-3 flex items-center justify-between border-t border-slate-100 p-5 pt-0 text-xs dark:border-slate-800"
                >
                    <span class="text-[11px] text-slate-400">{{ item.completionDate }}</span>
                    <div class="flex items-center gap-1">
                        <a
                            v-if="item.projectUrl"
                            :href="item.projectUrl"
                            target="_blank"
                            rel="noreferrer"
                            title="Buka Link Proyek"
                            class="rounded-lg p-1.5 text-blue-700 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-slate-800"
                        >
                            <span class="material-symbols-outlined text-[18px]">launch</span>
                        </a>
                        <button
                            title="Edit Portofolio"
                            class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-blue-700 dark:hover:bg-slate-800"
                            @click="openEditModal(item)"
                        >
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                        <button
                            title="Hapus Portofolio"
                            class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/50"
                            @click="remove(item)"
                        >
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / edit modal -->
        <Modal
            :is-open="isModalOpen"
            :title="editingItem ? 'Edit Portofolio' : 'Tambah Portofolio Baru'"
            subtitle="Publikasikan hasil pengerjaan proyek software."
            @close="isModalOpen = false"
        >
            <form class="space-y-4 text-xs sm:text-sm" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass">Judul Case Study *</label>
                        <input
                            v-model="form.title"
                            required
                            placeholder="Contoh: Platform Telemedicine SehatPlus"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.title" class="mt-1 text-[11px] text-rose-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label :class="labelClass">Nama Klien</label>
                        <input v-model="form.client" placeholder="Contoh: RS Medika Utama" :class="inputClass" />
                        <p v-if="form.errors.client" class="mt-1 text-[11px] text-rose-600">{{ form.errors.client }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass">Kategori Proyek</label>
                        <input
                            v-model="form.category"
                            placeholder="Enterprise ERP / Mobile App / E-Commerce"
                            :class="inputClass"
                        />
                    </div>

                    <div>
                        <label :class="labelClass">Tanggal Penyelesaian</label>
                        <input v-model="form.completion_date" type="date" :class="inputClass" />
                    </div>
                </div>

                <div>
                    <label :class="labelClass">URL Gambar / Tangkapan Layar</label>
                    <input v-model="form.image_url" placeholder="https://images.unsplash.com/..." :class="inputClass" />
                </div>

                <div>
                    <label :class="labelClass">URL Demonstrasi / Live Link</label>
                    <input v-model="form.project_url" placeholder="https://..." :class="inputClass" />
                </div>

                <div>
                    <label :class="labelClass">Teknologi yang Digunakan (Pisahkan koma)</label>
                    <input
                        v-model="form.technologies"
                        placeholder="React, TypeScript, Tailwind CSS, Docker"
                        :class="inputClass"
                    />
                </div>

                <div>
                    <label :class="labelClass">Deskripsi &amp; Dampak Solusi</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Jelaskan masalah klien dan arsitektur yang dibangun..."
                        :class="textareaClass"
                    />
                </div>

                <div class="flex items-center gap-6 pt-1">
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                        <input
                            v-model="form.featured"
                            type="checkbox"
                            class="rounded text-blue-600 focus:ring-blue-500"
                        />
                        <span>Tampilkan sebagai Featured Showcase</span>
                    </label>

                    <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                        <input
                            v-model="form.published"
                            type="checkbox"
                            class="rounded text-blue-600 focus:ring-blue-500"
                        />
                        <span>Publikasikan ke Website Publik</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="isModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-[#1e40af] px-5 py-2 text-xs font-bold text-white shadow-sm transition-all hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Portofolio' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
