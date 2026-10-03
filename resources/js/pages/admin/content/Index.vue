<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import {
    destroy as contentDestroy,
    store as contentStore,
    update as contentUpdate,
} from '@/routes/contents';
import type {
    ContentItem,
    ContentPlatform,
    ContentStatus,
    MemberOption,
} from '@/types';
import { getContentStatusBadge } from '@/utils/formatters';

/**
 * Editorial content calendar: Kanban workflow + list view.
 *
 * Ported from `SocialMediaPage.tsx`. Differences from the template:
 * - Platform options use generic names. The template hardcoded account handles
 *   ("Instagram (@alfatech.digital)"), which would be wrong for other teams.
 * - `media_url` and `notes` inputs were added. Both are columns the API accepts
 *   but the template's modal never rendered, so they were unreachable.
 * - `assignee_id` can be cleared ("Belum ditentukan"); the template always
 *   forced a member.
 * - Badge text comes from the Resource (`statusLabel`), colours from
 *   `getContentStatusBadge()`.
 */
const props = defineProps<{
    contents: ContentItem[];
    members: MemberOption[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const viewMode = ref<'kanban' | 'list'>('kanban');
const platformFilter = ref('all');
const searchQuery = ref('');

const isModalOpen = ref(false);
const editingContent = ref<ContentItem | null>(null);

const platforms: ContentPlatform[] = [
    'instagram',
    'linkedin',
    'tiktok',
    'facebook',
];

const statusOptions: { value: ContentStatus; label: string }[] = [
    { value: 'idea', label: 'Ide Konten' },
    { value: 'draft', label: 'Draf & Script' },
    { value: 'review', label: 'Review Desain/Video' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'scheduled', label: 'Terjadwal' },
    { value: 'published', label: 'Tayang (Published)' },
];

const kanbanColumns: { id: ContentStatus; title: string; accent: string }[] = [
    { id: 'idea', title: 'Ide Konten', accent: 'border-t-slate-400' },
    { id: 'draft', title: 'Draf & Script', accent: 'border-t-zinc-500' },
    {
        id: 'review',
        title: 'Review Desain/Video',
        accent: 'border-t-amber-500',
    },
    { id: 'approved', title: 'Disetujui', accent: 'border-t-indigo-500' },
    { id: 'scheduled', title: 'Terjadwal', accent: 'border-t-blue-500' },
    {
        id: 'published',
        title: 'Tayang (Published)',
        accent: 'border-t-emerald-500',
    },
];

const filteredContents = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.contents.filter((content) => {
        if (
            platformFilter.value !== 'all' &&
            content.platform !== platformFilter.value
        ) {
            return false;
        }

        if (!query) {
            return true;
        }

        return (
            content.title.toLowerCase().includes(query) ||
            (content.caption ?? '').toLowerCase().includes(query)
        );
    });
});

function columnContents(status: ContentStatus): ContentItem[] {
    return filteredContents.value.filter(
        (content) => content.status === status,
    );
}

function memberName(id: number | null): string {
    if (id === null) {
        return 'Belum ditentukan';
    }

    return (
        props.members.find((member) => member.id === id)?.name ??
        'Tim Media Sosial'
    );
}

const inputClass =
    'w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none';
const textareaClass =
    'w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none leading-relaxed';
const labelClass =
    'block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1';

const form = useForm({
    title: '',
    caption: '',
    platform: 'instagram' as ContentPlatform,
    content_type: 'Carousel Infografis',
    status: 'draft' as ContentStatus,
    scheduled_at: 'Besok, 10:00 WIB',
    assignee_id: null as number | null,
    media_url: '',
    notes: '',
});

function defaultAssigneeId(): number | null {
    return props.members[0]?.id ?? null;
}

function openCreateModal(): void {
    editingContent.value = null;
    form.clearErrors();
    form.title = '';
    form.caption = '';
    form.platform = 'instagram';
    form.content_type = 'Reels / Video Singkat';
    form.status = 'idea';
    form.scheduled_at = 'Besok, 10:00 WIB';
    form.assignee_id = defaultAssigneeId();
    form.media_url = '';
    form.notes = '';
    isModalOpen.value = true;
}

function openEditModal(content: ContentItem): void {
    editingContent.value = content;

    form.title = content.title;
    form.caption = content.caption ?? '';
    form.platform = content.platform;
    form.content_type = content.contentType ?? '';
    form.status = content.status;
    form.scheduled_at = content.scheduledAt ?? '';
    form.assignee_id = content.assigneeId;
    form.media_url = content.mediaUrl ?? '';
    form.notes = content.notes ?? '';

    form.clearErrors();
    isModalOpen.value = true;
}

function saveContent(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isModalOpen.value = false;
            editingContent.value = null;
        },
    };

    if (editingContent.value) {
        form.put(
            contentUpdate.url({
                current_team: teamSlug.value,
                content: editingContent.value.id,
            }),
            options,
        );

        return;
    }

    form.post(contentStore.url({ current_team: teamSlug.value }), options);
}

function changeStatus(content: ContentItem, status: ContentStatus): void {
    if (content.status === status) {
        return;
    }

    router.put(
        contentUpdate.url({
            current_team: teamSlug.value,
            content: content.id,
        }),
        {
            title: content.title,
            caption: content.caption,
            platform: content.platform,
            content_type: content.contentType,
            status,
            scheduled_at: content.scheduledAt,
            assignee_id: content.assigneeId,
            media_url: content.mediaUrl,
            notes: content.notes,
        },
        { preserveScroll: true },
    );
}

function removeContent(content: ContentItem): void {
    if (!window.confirm(`Hapus jadwal konten "${content.title}"?`)) {
        return;
    }

    router.delete(
        contentDestroy.url({
            current_team: teamSlug.value,
            content: content.id,
        }),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="Kalender Konten" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                >
                    Kalender Konten &amp; Media Sosial
                </h1>
                <p
                    class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    Alur kerja editorial internal untuk Instagram, LinkedIn,
                    TikTok, dan Facebook.
                </p>
            </div>
            <button
                class="flex items-center gap-1.5 self-start rounded-xl bg-[#1e40af] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-700 sm:self-auto sm:text-sm"
                @click="openCreateModal"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Buat Rencana Konten</span>
            </button>
        </div>

        <!-- Filters & view switcher -->
        <div
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div
                class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-semibold sm:text-sm"
            >
                <button
                    :class="[
                        'rounded-lg px-3 py-1.5 text-[11px] font-bold tracking-wider uppercase transition-all',
                        platformFilter === 'all'
                            ? 'bg-[#1e40af] text-white shadow-xs'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                    ]"
                    @click="platformFilter = 'all'"
                >
                    Semua Platform
                </button>
                <button
                    v-for="platform in platforms"
                    :key="platform"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-[11px] font-bold tracking-wider uppercase transition-all',
                        platformFilter === platform
                            ? 'bg-[#1e40af] text-white shadow-xs'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                    ]"
                    @click="platformFilter = platform"
                >
                    {{ platform }}
                </button>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative w-full sm:w-64">
                    <span
                        class="material-symbols-outlined absolute top-2 left-3 text-[18px] text-slate-400"
                        >search</span
                    >
                    <input
                        v-model="searchQuery"
                        placeholder="Cari rencana konten..."
                        class="h-9 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    />
                </div>

                <div
                    class="flex items-center rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
                >
                    <button
                        :class="[
                            'rounded-lg p-1.5 transition-all',
                            viewMode === 'kanban'
                                ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-100'
                                : 'text-slate-500 dark:text-slate-400',
                        ]"
                        title="Kanban View"
                        @click="viewMode = 'kanban'"
                    >
                        <span class="material-symbols-outlined text-[18px]"
                            >view_kanban</span
                        >
                    </button>
                    <button
                        :class="[
                            'rounded-lg p-1.5 transition-all',
                            viewMode === 'list'
                                ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-100'
                                : 'text-slate-500 dark:text-slate-400',
                        ]"
                        title="List View"
                        @click="viewMode = 'list'"
                    >
                        <span class="material-symbols-outlined text-[18px]"
                            >list</span
                        >
                    </button>
                </div>
            </div>
        </div>

        <!-- Kanban -->
        <div
            v-if="viewMode === 'kanban'"
            class="grid grid-cols-1 items-start gap-3.5 md:grid-cols-3 lg:grid-cols-6"
        >
            <div
                v-for="column in kanbanColumns"
                :key="column.id"
                :class="[
                    'space-y-3 rounded-2xl border border-t-4 border-slate-200 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/60',
                    column.accent,
                ]"
            >
                <div class="flex items-center justify-between pb-1">
                    <h3
                        class="truncate text-[11px] font-bold tracking-tight text-slate-800 uppercase dark:text-slate-200"
                    >
                        {{ column.title }}
                    </h3>
                    <span
                        class="rounded-full bg-white px-1.5 text-[10px] font-bold text-slate-700 shadow-xs dark:bg-slate-800 dark:text-slate-200"
                    >
                        {{ columnContents(column.id).length }}
                    </span>
                </div>

                <div class="min-h-[120px] space-y-2.5">
                    <div
                        v-if="columnContents(column.id).length === 0"
                        class="flex h-20 items-center justify-center rounded-xl border border-dashed border-slate-200 text-[10px] text-slate-400 dark:border-slate-700"
                    >
                        Kosong
                    </div>

                    <div
                        v-for="content in columnContents(column.id)"
                        :key="content.id"
                        class="space-y-2 rounded-xl border border-slate-100 bg-white p-3 shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 uppercase dark:bg-blue-950/60 dark:text-blue-300"
                            >
                                {{
                                    platformFilter === 'all'
                                        ? content.platform
                                        : content.platformLabel
                                }}
                            </span>
                            <span
                                class="max-w-[80px] truncate text-[10px] text-slate-400"
                            >
                                {{ content.contentType ?? '-' }}
                            </span>
                        </div>

                        <h4
                            class="line-clamp-2 text-xs leading-snug font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ content.title }}
                        </h4>

                        <div
                            class="flex items-center gap-1 rounded bg-slate-50 p-1.5 text-[10px] text-slate-500 dark:bg-slate-800/60 dark:text-slate-400"
                        >
                            <span class="material-symbols-outlined text-[13px]"
                                >schedule</span
                            >
                            <span class="truncate">{{
                                content.scheduledAt ?? 'Belum dijadwalkan'
                            }}</span>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-slate-100 pt-1 text-[10px] dark:border-slate-800"
                        >
                            <span
                                class="max-w-[70px] truncate text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    content.assigneeName ??
                                    memberName(content.assigneeId)
                                }}
                            </span>
                            <div class="flex items-center gap-1">
                                <button
                                    class="p-1 text-slate-400 hover:text-blue-600"
                                    @click="openEditModal(content)"
                                >
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        >edit</span
                                    >
                                </button>
                                <button
                                    class="p-1 text-slate-400 hover:text-rose-600"
                                    @click="removeContent(content)"
                                >
                                    <span
                                        class="material-symbols-outlined text-[14px]"
                                        >delete</span
                                    >
                                </button>
                            </div>
                        </div>

                        <select
                            :value="content.status"
                            class="w-full cursor-pointer rounded bg-slate-100 px-1.5 py-1 text-[10px] text-slate-700 focus:outline-none dark:bg-slate-800 dark:text-slate-200"
                            @change="
                                changeStatus(
                                    content,
                                    ($event.target as HTMLSelectElement)
                                        .value as ContentStatus,
                                )
                            "
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                Pindah: {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- List view -->
        <div
            v-else
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-slate-100 bg-slate-50 font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-4 py-3">
                                Judul Konten &amp; Kategori
                            </th>
                            <th class="px-3 py-3">Platform</th>
                            <th class="px-3 py-3">Jadwal Tayang</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Penanggung Jawab</th>
                            <th class="px-3 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr v-if="filteredContents.length === 0">
                            <td
                                colspan="6"
                                class="py-8 text-center text-slate-400"
                            >
                                Belum ada rencana konten yang cocok.
                            </td>
                        </tr>

                        <tr
                            v-for="content in filteredContents"
                            :key="content.id"
                            class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                        >
                            <td class="max-w-xs px-4 py-3">
                                <div
                                    class="truncate font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ content.title }}
                                </div>
                                <div
                                    class="truncate text-[11px] text-slate-500 dark:text-slate-400"
                                >
                                    {{ content.contentType ?? '-' }}
                                </div>
                            </td>
                            <td
                                class="px-3 py-3 text-[11px] font-bold text-blue-700 uppercase dark:text-blue-400"
                            >
                                {{ content.platform }}
                            </td>
                            <td
                                class="px-3 py-3 font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ content.scheduledAt ?? '-' }}
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold',
                                        getContentStatusBadge(content.status)
                                            .class,
                                    ]"
                                >
                                    <span
                                        class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current"
                                    ></span>
                                    {{ content.statusLabel }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-3 font-medium text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    content.assigneeName ??
                                    memberName(content.assigneeId)
                                }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <button
                                        class="rounded p-1 text-slate-400 hover:text-blue-600"
                                        @click="openEditModal(content)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[18px]"
                                            >edit</span
                                        >
                                    </button>
                                    <button
                                        class="rounded p-1 text-slate-400 hover:text-rose-600"
                                        @click="removeContent(content)"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[18px]"
                                            >delete</span
                                        >
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: create / edit content -->
        <Modal
            :is-open="isModalOpen"
            :title="
                editingContent
                    ? 'Edit Jadwal Konten'
                    : 'Buat Jadwal Konten Baru'
            "
            subtitle="Rencanakan materi publikasi digital marketing."
            @close="isModalOpen = false"
        >
            <form
                class="space-y-4 text-xs sm:text-sm"
                @submit.prevent="saveContent"
            >
                <div>
                    <label :class="labelClass">Judul / Topik Konten *</label>
                    <input
                        v-model="form.title"
                        required
                        placeholder="Contoh: Tips Migrasi Cloud untuk UKM Indonesia"
                        :class="inputClass"
                    />
                    <p
                        v-if="form.errors.title"
                        class="mt-1 text-[11px] text-rose-600"
                    >
                        {{ form.errors.title }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass">Platform Media Sosial</label>
                        <select
                            v-model="form.platform"
                            :class="[inputClass, 'cursor-pointer capitalize']"
                        >
                            <option
                                v-for="platform in platforms"
                                :key="platform"
                                :value="platform"
                            >
                                {{ platform }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.platform"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.platform }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Format Konten</label>
                        <input
                            v-model="form.content_type"
                            placeholder="Carousel / Reels Video / PDF Slide"
                            :class="inputClass"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label :class="labelClass">Status Editorial</label>
                        <select
                            v-model="form.status"
                            :class="[inputClass, 'cursor-pointer']"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label :class="labelClass">Jadwal Tayang</label>
                        <input
                            v-model="form.scheduled_at"
                            placeholder="Contoh: Besok, 10:00 WIB"
                            :class="inputClass"
                        />
                    </div>

                    <div>
                        <label :class="labelClass">PIC Pembuat Konten</label>
                        <select
                            v-model="form.assignee_id"
                            :class="[inputClass, 'cursor-pointer']"
                        >
                            <option :value="null">Belum ditentukan</option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.assignee_id"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.assignee_id }}
                        </p>
                    </div>
                </div>

                <div>
                    <label :class="labelClass">Draft Caption / Naskah</label>
                    <textarea
                        v-model="form.caption"
                        rows="4"
                        placeholder="Tuliskan naskah caption, call-to-action (CTA), dan hashtag..."
                        :class="textareaClass"
                    />
                </div>

                <div>
                    <label :class="labelClass"
                        >URL Media (draft aset / Drive)</label
                    >
                    <input
                        v-model="form.media_url"
                        placeholder="https://..."
                        :class="inputClass"
                    />
                    <p
                        v-if="form.errors.media_url"
                        class="mt-1 text-[11px] text-rose-600"
                    >
                        {{ form.errors.media_url }}
                    </p>
                </div>

                <div>
                    <label :class="labelClass">Catatan Internal</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="Catatan revisi, brief desain, atau PIC approval..."
                        :class="textareaClass"
                    />
                </div>

                <div
                    class="flex items-center justify-end gap-3 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
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
                        {{
                            form.processing
                                ? 'Menyimpan...'
                                : 'Simpan Jadwal Konten'
                        }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
