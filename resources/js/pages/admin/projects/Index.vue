<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import {
    destroy as projectDestroy,
    store as projectStore,
    update as projectUpdate,
} from '@/routes/projects';
import {
    destroy as taskDestroy,
    store as taskStore,
    update as taskUpdate,
} from '@/routes/tasks';
import type {
    MemberOption,
    Project,
    ProjectStatus,
    Task,
    TaskPriority,
} from '@/types';
import { formatRupiah, getProjectStatusBadge } from '@/utils/formatters';

/**
 * Project board with an inline quick-preview drawer.
 *
 * Ported from `ProjectsPage.tsx`. Differences from the template:
 * - The selected project lives in local state; the template held it in `App.tsx`.
 * - Badge text comes from the Resource (`statusLabel`), colours from
 *   `getProjectStatusBadge()` — the server owns the Indonesian labels.
 * - "Ekspor CSV" really exports the filtered rows instead of a stub `alert()`.
 *   It is generated client-side, so no backend endpoint is needed.
 * - The PIC WhatsApp link uses the member's real phone number from `members`
 *   (the template hardcoded a placeholder number).
 * - Client-side `required` on the client name is kept from the template even
 *   though `SaveProjectRequest` allows null.
 */
const props = defineProps<{
    projects: Project[];
    tasks: Task[];
    members: MemberOption[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const activeTab = ref('all');
const searchQuery = ref('');
const selectedPicId = ref('all');
const sortBy = ref<'deadline' | 'value' | 'progress'>('deadline');

const isModalOpen = ref(false);
const editingProject = ref<Project | null>(null);
const selectedProject = ref<Project | null>(null);

const newTaskTitle = ref('');
const newTaskPriority = ref<TaskPriority>('medium');

function countByStatus(status: ProjectStatus): number {
    return props.projects.filter((project) => project.status === status).length;
}

const tabs = computed(() => [
    { id: 'all', label: 'Semua Proyek', count: props.projects.length },
    { id: 'deal', label: 'Deal', count: countByStatus('deal') },
    {
        id: 'development',
        label: 'Development',
        count: countByStatus('development'),
    },
    { id: 'review', label: 'Review', count: countByStatus('review') },
    { id: 'completed', label: 'Selesai', count: countByStatus('completed') },
    { id: 'cancelled', label: 'Dibatalkan', count: countByStatus('cancelled') },
]);

const filteredProjects = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.projects
        .filter((project) => {
            if (
                activeTab.value !== 'all' &&
                project.status !== activeTab.value
            ) {
                return false;
            }

            if (
                selectedPicId.value !== 'all' &&
                String(project.picId) !== selectedPicId.value
            ) {
                return false;
            }

            if (!query) {
                return true;
            }

            return (
                project.name.toLowerCase().includes(query) ||
                (project.clientName ?? '').toLowerCase().includes(query) ||
                project.technologies.some((tech) =>
                    tech.toLowerCase().includes(query),
                )
            );
        })
        .sort((a, b) => {
            if (sortBy.value === 'value') {
                return b.projectValue - a.projectValue;
            }

            if (sortBy.value === 'progress') {
                return b.progress - a.progress;
            }

            return (a.deadline ?? '9999-12-31').localeCompare(
                b.deadline ?? '9999-12-31',
            );
        });
});

const projectTasks = computed(() =>
    selectedProject.value
        ? props.tasks.filter(
              (task) => task.projectId === selectedProject.value?.id,
          )
        : [],
);

const doneTaskCount = computed(
    () => projectTasks.value.filter((task) => task.status === 'done').length,
);

const selectedPicMember = computed(() =>
    props.members.find((member) => member.id === selectedProject.value?.picId),
);

const whatsappLink = computed(() => {
    const project = selectedProject.value;
    const member = selectedPicMember.value;

    if (!project || !member?.phone) {
        return null;
    }

    const digits = member.phone.replace(/[^0-9]/g, '').replace(/^0/, '62');

    if (!digits) {
        return null;
    }

    const text = `Halo ${member.name}, mohon update untuk proyek ${project.name}.`;

    return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`;
});

const inputClass =
    'w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none';
const textareaClass =
    'w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none leading-relaxed';
const labelClass =
    'block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1';

function today(): string {
    return new Date().toISOString().split('T')[0];
}

function daysFromNow(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return date.toISOString().split('T')[0];
}

function defaultPicId(): number | null {
    return props.members[0]?.id ?? null;
}

const form = useForm({
    name: '',
    client_name: '',
    project_value: 35_000_000,
    status: 'development' as ProjectStatus,
    // `progress` is a reserved key on Inertia's form object (upload progress),
    // so the field is named `progress_pct` and mapped back on submit.
    progress_pct: 0,
    start_date: today(),
    deadline: daysFromNow(45),
    pic_id: defaultPicId(),
    technologies: 'React, TypeScript, Tailwind CSS',
    description: '',
    notes: '',
});

form.transform((data) => ({
    name: data.name,
    client_name: data.client_name,
    project_value: data.project_value,
    status: data.status,
    progress: data.progress_pct,
    start_date: data.start_date,
    deadline: data.deadline,
    pic_id: data.pic_id,
    description: data.description,
    notes: data.notes,
    // The API expects `technologies: string[]`; the form edits a comma-separated string.
    technologies: data.technologies
        .split(',')
        .map((tech) => tech.trim())
        .filter(Boolean),
}));

const taskForm = useForm({
    project_id: 0,
    title: '',
    assignee_id: null as number | null,
    priority: 'medium' as TaskPriority,
    status: 'todo' as Task['status'],
    due_date: null as string | null,
});

/**
 * The server validates the field as `progress`, but that name is reserved on
 * Inertia's form object, so the local field is `progress_pct`. The validation
 * error is therefore keyed by the wire name and has to be read explicitly.
 */
const progressError = computed(
    () =>
        (form.errors as unknown as Record<string, string | undefined>).progress,
);

function openCreateModal(): void {
    editingProject.value = null;
    form.clearErrors();
    form.name = '';
    form.client_name = '';
    form.project_value = 35_000_000;
    form.status = 'development';
    form.progress_pct = 0;
    form.start_date = today();
    form.deadline = daysFromNow(45);
    form.pic_id = defaultPicId();
    form.technologies = 'React, TypeScript, Tailwind CSS';
    form.description = '';
    form.notes = '';
    isModalOpen.value = true;
}

function openEditModal(project: Project): void {
    editingProject.value = project;

    form.name = project.name;
    form.client_name = project.clientName ?? '';
    form.project_value = project.projectValue;
    form.status = project.status;
    form.progress_pct = project.progress;
    form.start_date = project.startDate ?? today();
    form.deadline = project.deadline ?? '';
    form.pic_id = project.picId;
    form.technologies = project.technologies.join(', ');
    form.description = project.description ?? '';
    form.notes = project.notes ?? '';

    form.clearErrors();
    isModalOpen.value = true;
}

function saveProject(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isModalOpen.value = false;
            editingProject.value = null;
        },
    };

    if (editingProject.value) {
        form.put(
            projectUpdate.url({
                current_team: teamSlug.value,
                project: editingProject.value.id,
            }),
            options,
        );

        return;
    }

    form.post(projectStore.url({ current_team: teamSlug.value }), options);
}

function removeProject(project: Project): void {
    if (
        !window.confirm(
            `Apakah Anda yakin ingin menghapus proyek "${project.name}"?`,
        )
    ) {
        return;
    }

    router.delete(
        projectDestroy.url({
            current_team: teamSlug.value,
            project: project.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedProject.value?.id === project.id) {
                    selectedProject.value = null;
                }
            },
        },
    );
}

function addTask(): void {
    const project = selectedProject.value;

    if (!project || !newTaskTitle.value.trim()) {
        return;
    }

    taskForm.project_id = project.id;
    taskForm.title = newTaskTitle.value.trim();
    taskForm.assignee_id = project.picId;
    taskForm.priority = newTaskPriority.value;
    taskForm.status = 'todo';
    taskForm.due_date = project.deadline;

    taskForm.post(taskStore.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        onSuccess: () => {
            newTaskTitle.value = '';
            newTaskPriority.value = 'medium';
        },
    });
}

function toggleTaskStatus(task: Task): void {
    router.put(
        taskUpdate.url({ current_team: teamSlug.value, task: task.id }),
        {
            project_id: task.projectId,
            title: task.title,
            description: task.description,
            assignee_id: task.assigneeId,
            priority: task.priority,
            status: task.status === 'done' ? 'todo' : 'done',
            due_date: task.dueDate,
        },
        { preserveScroll: true },
    );
}

function removeTask(task: Task): void {
    router.delete(
        taskDestroy.url({ current_team: teamSlug.value, task: task.id }),
        {
            preserveScroll: true,
        },
    );
}

function progressBarClass(progress: number): string {
    if (progress > 80) {
        return 'bg-emerald-500';
    }

    if (progress > 50) {
        return 'bg-blue-600';
    }

    return 'bg-amber-500';
}

function exportCsv(): void {
    const header = [
        'Nama Proyek',
        'Klien',
        'Status',
        'Progress (%)',
        'Nilai Kontrak',
        'Mulai',
        'Deadline',
        'PIC',
        'Teknologi',
    ];

    const rows = filteredProjects.value.map((project) => [
        project.name,
        project.clientName ?? '',
        project.statusLabel,
        String(project.progress),
        String(project.projectValue),
        project.startDate ?? '',
        project.deadline ?? '',
        project.picName ?? '',
        project.technologies.join('; '),
    ]);

    const csv = [header, ...rows]
        .map((row) =>
            row.map((cell) => `"${cell.replace(/"/g, '""')}"`).join(','),
        )
        .join('\r\n');

    // The BOM keeps Excel from mangling the Indonesian characters.
    const blob = new Blob([`\uFEFF${csv}`], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `proyek-${today()}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Manajemen Proyek" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                >
                    Manajemen Proyek
                </h1>
                <p
                    class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    Kelola siklus pengembangan, status kontrak, teknologi, dan
                    deliverable software.
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200 sm:text-sm dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    @click="exportCsv"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >file_download</span
                    >
                    <span class="hidden sm:inline">Ekspor CSV</span>
                </button>
                <button
                    class="flex items-center gap-1.5 rounded-xl bg-[#1e40af] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-700 sm:text-sm"
                    @click="openCreateModal"
                >
                    <span class="material-symbols-outlined text-[18px]"
                        >add</span
                    >
                    <span>Buat Proyek Baru</span>
                </button>
            </div>
        </div>

        <!-- Status tabs -->
        <div
            class="flex items-center gap-2 overflow-x-auto border-b border-slate-200 pb-1 text-xs font-semibold sm:text-sm dark:border-slate-800"
        >
            <button
                v-for="tab in tabs"
                :key="tab.id"
                :class="[
                    'flex items-center gap-1.5 rounded-lg px-3 py-2 whitespace-nowrap transition-all',
                    activeTab === tab.id
                        ? 'bg-[#1e40af] text-white shadow-xs'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                ]"
                @click="activeTab = tab.id"
            >
                <span>{{ tab.label }}</span>
                <span
                    :class="[
                        'rounded-full px-1.5 text-[10px]',
                        activeTab === tab.id
                            ? 'bg-white/20 text-white'
                            : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                    ]"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- Search & filters -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div class="relative md:col-span-6">
                <span
                    class="material-symbols-outlined absolute top-2.5 left-3 text-[18px] text-slate-400"
                    >search</span
                >
                <input
                    v-model="searchQuery"
                    placeholder="Cari nama proyek, klien, atau teknologi..."
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                />
            </div>

            <div class="md:col-span-3">
                <select
                    v-model="selectedPicId"
                    :class="[
                        inputClass,
                        'cursor-pointer text-slate-700 dark:text-slate-300',
                    ]"
                >
                    <option value="all">Semua PIC</option>
                    <option
                        v-for="member in members"
                        :key="member.id"
                        :value="String(member.id)"
                    >
                        PIC: {{ member.name }}
                    </option>
                </select>
            </div>

            <div class="md:col-span-3">
                <select
                    v-model="sortBy"
                    :class="[
                        inputClass,
                        'cursor-pointer text-slate-700 dark:text-slate-300',
                    ]"
                >
                    <option value="deadline">Urutkan: Deadline Terdekat</option>
                    <option value="value">
                        Urutkan: Nilai Kontrak Tertinggi
                    </option>
                    <option value="progress">
                        Urutkan: Progress Tertinggi
                    </option>
                </select>
            </div>
        </div>

        <!-- Table + quick preview drawer -->
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <div
                :class="
                    selectedProject
                        ? 'lg:col-span-7 xl:col-span-8'
                        : 'lg:col-span-12'
                "
            >
                <div
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-slate-100 bg-slate-50 font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">
                                        Nama Proyek &amp; Klien
                                    </th>
                                    <th class="px-3 py-3">Status Kontrak</th>
                                    <th class="px-3 py-3">Progress</th>
                                    <th class="px-3 py-3">Nilai Kontrak</th>
                                    <th class="px-3 py-3">Deadline</th>
                                    <th class="px-3 py-3">PIC</th>
                                    <th class="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr v-if="filteredProjects.length === 0">
                                    <td
                                        colspan="7"
                                        class="py-8 text-center text-slate-400"
                                    >
                                        Tidak ada proyek yang sesuai dengan
                                        kriteria filter.
                                    </td>
                                </tr>

                                <tr
                                    v-for="project in filteredProjects"
                                    :key="project.id"
                                    :class="[
                                        'cursor-pointer transition-colors',
                                        selectedProject?.id === project.id
                                            ? 'bg-blue-50/80 dark:bg-blue-950/40'
                                            : 'hover:bg-slate-50/70 dark:hover:bg-slate-800/50',
                                    ]"
                                    @click="selectedProject = project"
                                >
                                    <td class="max-w-[220px] px-4 py-3.5">
                                        <div
                                            class="truncate text-[13px] font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ project.name }}
                                        </div>
                                        <div
                                            class="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                project.clientName ??
                                                'Tanpa klien'
                                            }}
                                        </div>
                                        <div
                                            class="mt-1.5 flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="tech in project.technologies.slice(
                                                    0,
                                                    3,
                                                )"
                                                :key="tech"
                                                class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                            >
                                                {{ tech }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3.5 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold',
                                                getProjectStatusBadge(
                                                    project.status,
                                                ).class,
                                            ]"
                                        >
                                            <span
                                                class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current"
                                            ></span>
                                            {{ project.statusLabel }}
                                        </span>
                                    </td>

                                    <td class="min-w-[110px] px-3 py-3.5">
                                        <div
                                            class="mb-1 flex justify-between text-[11px] font-bold text-slate-700 dark:text-slate-200"
                                        >
                                            <span>{{ project.progress }}%</span>
                                        </div>
                                        <div
                                            class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                        >
                                            <div
                                                :class="[
                                                    'h-full rounded-full',
                                                    progressBarClass(
                                                        project.progress,
                                                    ),
                                                ]"
                                                :style="{
                                                    width: `${project.progress}%`,
                                                }"
                                            ></div>
                                        </div>
                                    </td>

                                    <td
                                        class="px-3 py-3.5 font-bold whitespace-nowrap text-slate-900 dark:text-slate-100"
                                    >
                                        {{ formatRupiah(project.projectValue) }}
                                    </td>

                                    <td class="px-3 py-3.5 whitespace-nowrap">
                                        <div
                                            class="font-medium text-slate-800 dark:text-slate-200"
                                        >
                                            {{ project.deadline ?? '-' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            Mulai:
                                            {{ project.startDate ?? '-' }}
                                        </div>
                                    </td>

                                    <td class="px-3 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <div
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                                            >
                                                {{
                                                    (
                                                        project.picName ?? '?'
                                                    ).charAt(0)
                                                }}
                                            </div>
                                            <div>
                                                <div
                                                    class="max-w-[100px] truncate font-semibold text-slate-800 dark:text-slate-200"
                                                >
                                                    {{
                                                        project.picName ??
                                                        'Belum ada PIC'
                                                    }}
                                                </div>
                                                <div
                                                    class="text-[10px] text-slate-400"
                                                >
                                                    {{
                                                        project.picRole ??
                                                        'Engineer'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td
                                        class="px-3 py-3.5 text-right whitespace-nowrap"
                                        @click.stop
                                    >
                                        <div
                                            class="flex items-center justify-end gap-1"
                                        >
                                            <button
                                                title="Edit Proyek"
                                                class="rounded p-1 text-slate-400 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-800"
                                                @click="openEditModal(project)"
                                            >
                                                <span
                                                    class="material-symbols-outlined text-[18px]"
                                                    >edit</span
                                                >
                                            </button>
                                            <button
                                                title="Hapus Proyek"
                                                class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/50"
                                                @click="removeProject(project)"
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
            </div>

            <!-- Quick preview drawer -->
            <div
                v-if="selectedProject"
                class="sticky top-20 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-lg lg:col-span-5 xl:col-span-4 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex items-start justify-between border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <div class="min-w-0 flex-1">
                        <span
                            class="text-[10px] font-bold tracking-wider text-blue-700 uppercase dark:text-blue-400"
                        >
                            Detail Proyek
                        </span>
                        <h3
                            class="mt-0.5 truncate text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ selectedProject.name }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ selectedProject.clientName ?? 'Tanpa klien' }}
                        </p>
                    </div>
                    <button
                        class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                        @click="selectedProject = null"
                    >
                        <span class="material-symbols-outlined text-[20px]"
                            >close</span
                        >
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50"
                    >
                        <span class="text-[11px] text-slate-400"
                            >Nilai Kontrak</span
                        >
                        <div
                            class="mt-0.5 text-sm font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ formatRupiah(selectedProject.projectValue) }}
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50"
                    >
                        <span class="text-[11px] text-slate-400"
                            >Deadline Rilis</span
                        >
                        <div
                            class="mt-0.5 text-sm font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ selectedProject.deadline ?? '-' }}
                        </div>
                    </div>
                </div>

                <div>
                    <div
                        class="mb-1.5 flex justify-between text-xs font-semibold"
                    >
                        <span class="text-slate-600 dark:text-slate-300"
                            >Progress Pengerjaan</span
                        >
                        <span class="font-bold text-blue-700 dark:text-blue-400"
                            >{{ selectedProject.progress }}%</span
                        >
                    </div>
                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-[#1e40af] transition-all duration-300"
                            :style="{ width: `${selectedProject.progress}%` }"
                        ></div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                        >
                            {{ (selectedProject.picName ?? '?').charAt(0) }}
                        </div>
                        <div>
                            <div
                                class="text-xs font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ selectedProject.picName ?? 'Belum ada PIC' }}
                            </div>
                            <div
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                {{ selectedProject.picRole ?? 'Project Lead' }}
                            </div>
                        </div>
                    </div>
                    <a
                        v-if="whatsappLink"
                        :href="whatsappLink"
                        target="_blank"
                        rel="noreferrer"
                        title="Hubungi PIC via WhatsApp"
                        class="rounded-lg bg-emerald-50 p-2 text-emerald-700 transition-colors hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        <span class="material-symbols-outlined text-[18px]"
                            >chat</span
                        >
                    </a>
                </div>

                <div class="space-y-2 text-xs">
                    <span class="font-bold text-slate-800 dark:text-slate-200"
                        >Deskripsi &amp; Ruang Lingkup:</span
                    >
                    <p
                        class="rounded-lg border border-slate-100 bg-slate-50 p-2.5 leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
                    >
                        {{
                            selectedProject.description ||
                            'Tidak ada deskripsi tambahan.'
                        }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span
                            v-for="tech in selectedProject.technologies"
                            :key="tech"
                            class="rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-300"
                        >
                            {{ tech }}
                        </span>
                    </div>
                </div>

                <!-- Sprint tasks -->
                <div
                    class="space-y-3 border-t border-slate-100 pt-2 dark:border-slate-800"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            Sprint &amp; Deliverable Tasks
                        </span>
                        <span
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            {{ doneTaskCount }}/{{ projectTasks.length }}
                            Selesai
                        </span>
                    </div>

                    <div
                        class="max-h-48 space-y-1.5 overflow-y-auto pr-1 text-xs"
                    >
                        <div
                            v-if="projectTasks.length === 0"
                            class="py-3 text-center text-xs text-slate-400"
                        >
                            Belum ada tugas di proyek ini.
                        </div>

                        <div
                            v-for="task in projectTasks"
                            :key="task.id"
                            class="flex items-start gap-2 rounded-lg border border-transparent p-2 transition-all hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-800/50"
                        >
                            <input
                                type="checkbox"
                                :checked="task.status === 'done'"
                                class="mt-0.5 cursor-pointer rounded text-blue-600 focus:ring-blue-500"
                                @change="toggleTaskStatus(task)"
                            />
                            <div class="min-w-0 flex-1">
                                <span
                                    :class="
                                        task.status === 'done'
                                            ? 'text-slate-400 line-through'
                                            : 'text-slate-800 dark:text-slate-200'
                                    "
                                    class="font-medium"
                                >
                                    {{ task.title }}
                                </span>
                                <div
                                    class="mt-0.5 flex items-center gap-2 text-[10px] text-slate-400"
                                >
                                    <span>{{ task.dueDate ?? '-' }}</span>
                                    <span
                                        :class="
                                            task.priority === 'high'
                                                ? 'font-bold text-rose-600 uppercase'
                                                : 'uppercase'
                                        "
                                    >
                                        {{ task.priorityLabel }}
                                    </span>
                                </div>
                            </div>
                            <button
                                class="p-0.5 text-slate-300 hover:text-rose-600"
                                @click="removeTask(task)"
                            >
                                <span
                                    class="material-symbols-outlined text-[14px]"
                                    >delete</span
                                >
                            </button>
                        </div>
                    </div>

                    <form class="flex gap-2 pt-1" @submit.prevent="addTask">
                        <input
                            v-model="newTaskTitle"
                            placeholder="+ Tambah tugas sprint..."
                            class="flex-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                        <select
                            v-model="newTaskPriority"
                            class="rounded-lg border border-slate-200 px-1.5 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                        <button
                            type="submit"
                            :disabled="
                                !newTaskTitle.trim() || taskForm.processing
                            "
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Tambah
                        </button>
                    </form>
                </div>

                <div
                    class="flex gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                    <button
                        class="flex-1 rounded-xl bg-slate-100 py-2 text-xs font-bold text-slate-800 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        @click="openEditModal(selectedProject)"
                    >
                        Edit Proyek
                    </button>
                    <button
                        class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600 transition-colors hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-300"
                        @click="removeProject(selectedProject)"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: create / edit project -->
        <Modal
            :is-open="isModalOpen"
            :title="
                editingProject ? 'Edit Proyek Software' : 'Buat Proyek Baru'
            "
            subtitle="Isi parameter proyek, alokasi PIC engineer, dan deliverable kontrak."
            @close="isModalOpen = false"
        >
            <form
                class="space-y-4 text-xs sm:text-sm"
                @submit.prevent="saveProject"
            >
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass">Nama Proyek *</label>
                        <input
                            v-model="form.name"
                            required
                            placeholder="Contoh: Sistem ERP Pergudangan"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass"
                            >Nama Klien / Perusahaan *</label
                        >
                        <input
                            v-model="form.client_name"
                            required
                            placeholder="Contoh: PT Logistik Nusantara"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.client_name"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.client_name }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label :class="labelClass">Nilai Kontrak (Rp)</label>
                        <input
                            v-model.number="form.project_value"
                            type="number"
                            min="0"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.project_value"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.project_value }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Status Proyek</label>
                        <select
                            v-model="form.status"
                            :class="[inputClass, 'cursor-pointer']"
                        >
                            <option value="lead">Lead</option>
                            <option value="negotiation">Negosiasi</option>
                            <option value="deal">Deal</option>
                            <option value="development">Pengembangan</option>
                            <option value="review">Review / UAT</option>
                            <option value="completed">Selesai</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>

                    <div>
                        <label :class="labelClass"
                            >Progress ({{ form.progress_pct }}%)</label
                        >
                        <input
                            v-model.number="form.progress_pct"
                            type="range"
                            min="0"
                            max="100"
                            class="mt-2 w-full cursor-pointer"
                        />
                        <p
                            v-if="progressError"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ progressError }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label :class="labelClass">Tanggal Mulai</label>
                        <input
                            v-model="form.start_date"
                            type="date"
                            :class="inputClass"
                        />
                    </div>

                    <div>
                        <label :class="labelClass">Deadline Selesai</label>
                        <input
                            v-model="form.deadline"
                            type="date"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.deadline"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.deadline }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">PIC Proyek</label>
                        <select
                            v-model="form.pic_id"
                            :class="[inputClass, 'cursor-pointer']"
                        >
                            <option :value="null">Belum ditentukan</option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name
                                }}{{
                                    member.jobTitle
                                        ? ` (${member.jobTitle})`
                                        : ''
                                }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.pic_id"
                            class="mt-1 text-[11px] text-rose-600"
                        >
                            {{ form.errors.pic_id }}
                        </p>
                    </div>
                </div>

                <div>
                    <label :class="labelClass"
                        >Teknologi yang Digunakan (Pisahkan koma)</label
                    >
                    <input
                        v-model="form.technologies"
                        placeholder="React, Node.js, PostgreSQL, Docker"
                        :class="inputClass"
                    />
                    <p
                        v-if="form.errors.technologies"
                        class="mt-1 text-[11px] text-rose-600"
                    >
                        {{ form.errors.technologies }}
                    </p>
                </div>

                <div>
                    <label :class="labelClass">Deskripsi Proyek</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Jelaskan kebutuhan fungsional dan arsitektur sistem..."
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
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Proyek' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
