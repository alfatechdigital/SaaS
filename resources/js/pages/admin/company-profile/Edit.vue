<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { update as companyProfileUpdate } from '@/routes/company-profile';
import { companyProfile as publicCompanyProfile } from '@/routes/public';
import type { CompanyFaq, CompanyProfile, CompanyService } from '@/types';

/**
 * CMS editor for the team's company profile.
 *
 * Ported from `CompanyProfilePage.tsx`. Differences from the template:
 * - Saving goes through Inertia (`form.put`) instead of Firestore.
 * - `description`, `contact`, `products`, and the remaining social links are not
 *   editable here but are still submitted so the server keeps their values.
 */
const props = defineProps<{
    profile: CompanyProfile | null;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const activeTab = ref<'editor' | 'preview'>('editor');

const form = useForm({
    company_name: props.profile?.companyName ?? '',
    description: props.profile?.description ?? '',
    about: props.profile?.about ?? '',
    services: (props.profile?.services ?? []) as CompanyService[],
    products: props.profile?.products ?? [],
    contact: props.profile?.contact ?? '',
    email: props.profile?.email ?? '',
    phone: props.profile?.phone ?? '',
    address: props.profile?.address ?? '',
    social_links: {
        website: props.profile?.socialLinks?.website ?? '',
        instagram: props.profile?.socialLinks?.instagram ?? '',
        linkedin: props.profile?.socialLinks?.linkedin ?? '',
        github: props.profile?.socialLinks?.github ?? '',
    },
    faq: (props.profile?.faq ?? []) as CompanyFaq[],
});

const whatsappLink = computed(() => {
    const digits = form.phone.replace(/[^0-9]/g, '');

    return digits ? `https://wa.me/${digits}` : null;
});

const inputClass =
    'w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none';
const textareaClass =
    'w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-600 focus:outline-none leading-relaxed';
const labelClass = 'block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1';
const cardClass =
    'bg-white dark:bg-slate-900 p-5 sm:p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-xs space-y-4';

function addService(): void {
    form.services.push({
        id: `srv-${Date.now()}`,
        name: 'Layanan Baru',
        desc: 'Deskripsi layanan baru...',
        icon: 'code',
    });
}

function removeService(index: number): void {
    form.services.splice(index, 1);
}

function addFaq(): void {
    form.faq.push({
        question: 'Pertanyaan umum baru?',
        answer: 'Jawaban penjelasan pertanyaan...',
    });
}

function removeFaq(index: number): void {
    form.faq.splice(index, 1);
}

function save(): void {
    form.put(companyProfileUpdate.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Profil Perusahaan" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100">
                    CMS Profil Perusahaan
                </h1>
                <p class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                    Kelola konten profil, layanan IT, kontak resmi, dan FAQ perusahaan.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    :href="publicCompanyProfile.url({ team: teamSlug })"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700 transition-all hover:border-slate-300 hover:text-blue-700 sm:text-sm dark:border-slate-700 dark:text-slate-300 dark:hover:text-blue-400"
                >
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                    <span>Buka Website Publik</span>
                </a>
                <button
                    type="button"
                    :class="[
                        'rounded-xl px-3.5 py-2 text-xs font-bold transition-all sm:text-sm',
                        activeTab === 'editor'
                            ? 'bg-[#1e40af] text-white shadow-xs'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700',
                    ]"
                    @click="activeTab = 'editor'"
                >
                    Formulir CMS
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-xl px-3.5 py-2 text-xs font-bold transition-all sm:text-sm',
                        activeTab === 'preview'
                            ? 'bg-[#1e40af] text-white shadow-xs'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700',
                    ]"
                    @click="activeTab = 'preview'"
                >
                    Live Preview
                </button>
            </div>
        </div>

        <!-- Editor -->
        <form v-if="activeTab === 'editor'" class="space-y-6" @submit.prevent="save">
            <div :class="cardClass">
                <h2 class="flex items-center gap-2 text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    <span class="material-symbols-outlined text-[20px] text-blue-700 dark:text-blue-400">domain</span>
                    <span>Identitas &amp; Informasi Utama</span>
                </h2>

                <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2 sm:text-sm">
                    <div>
                        <label :class="labelClass">Nama Resmi Perusahaan</label>
                        <input v-model="form.company_name" :class="inputClass" />
                        <p v-if="form.errors.company_name" class="mt-1 text-[11px] text-rose-600">
                            {{ form.errors.company_name }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Email Resmi</label>
                        <input v-model="form.email" type="email" :class="inputClass" />
                        <p v-if="form.errors.email" class="mt-1 text-[11px] text-rose-600">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass">Nomor Telepon / WhatsApp</label>
                        <input v-model="form.phone" :class="inputClass" />
                    </div>

                    <div>
                        <label :class="labelClass">Website URL</label>
                        <input v-model="form.social_links.website" :class="inputClass" />
                    </div>
                </div>

                <div class="text-xs sm:text-sm">
                    <label :class="labelClass">Alamat Kantor</label>
                    <input v-model="form.address" :class="inputClass" />
                </div>

                <div class="text-xs sm:text-sm">
                    <label :class="labelClass">Tentang Kami (About Story)</label>
                    <textarea v-model="form.about" rows="4" :class="textareaClass" />
                </div>
            </div>

            <!-- Services -->
            <div :class="cardClass">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        <span class="material-symbols-outlined text-[20px] text-blue-700 dark:text-blue-400">
                            design_services
                        </span>
                        <span>Layanan Unggulan Software House</span>
                    </h2>
                    <button
                        type="button"
                        class="flex items-center gap-1 text-xs font-bold text-blue-700 hover:text-blue-800 dark:text-blue-400"
                        @click="addService"
                    >
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        <span>Tambah Layanan</span>
                    </button>
                </div>

                <p v-if="form.errors.services" class="text-[11px] text-rose-600">{{ form.errors.services }}</p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div
                        v-for="(service, index) in form.services"
                        :key="service.id"
                        class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-800/40"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                Layanan #{{ index + 1 }}
                            </span>
                            <button
                                type="button"
                                class="p-1 text-rose-500 hover:text-rose-700"
                                title="Hapus layanan"
                                @click="removeService(index)"
                            >
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                            </button>
                        </div>
                        <input
                            v-model="service.name"
                            placeholder="Nama Layanan"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <textarea
                            v-model="service.desc"
                            rows="2"
                            placeholder="Deskripsi ruang lingkup layanan..."
                            class="w-full rounded-lg border border-slate-200 bg-white p-2.5 text-xs leading-relaxed text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        />
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <div :class="cardClass">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        <span class="material-symbols-outlined text-[20px] text-blue-700 dark:text-blue-400">help</span>
                        <span>Frequently Asked Questions (FAQ)</span>
                    </h2>
                    <button
                        type="button"
                        class="flex items-center gap-1 text-xs font-bold text-blue-700 hover:text-blue-800 dark:text-blue-400"
                        @click="addFaq"
                    >
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        <span>Tambah FAQ</span>
                    </button>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(item, index) in form.faq"
                        :key="index"
                        class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-800/40"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                Pertanyaan #{{ index + 1 }}
                            </span>
                            <button
                                type="button"
                                class="p-1 text-rose-500 hover:text-rose-700"
                                title="Hapus FAQ"
                                @click="removeFaq(index)"
                            >
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                            </button>
                        </div>
                        <input
                            v-model="item.question"
                            placeholder="Pertanyaan..."
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <textarea
                            v-model="item.answer"
                            rows="2"
                            placeholder="Jawaban resmi..."
                            class="w-full rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-900 focus:ring-2 focus:ring-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        />
                    </div>
                </div>
            </div>

            <div
                class="sticky bottom-4 flex items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur-md dark:border-slate-700 dark:bg-slate-900/95"
            >
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex items-center gap-2 rounded-xl bg-[#1e40af] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-blue-700 disabled:opacity-50 sm:text-sm"
                >
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan CMS' }}</span>
                </button>
            </div>
        </form>

        <!-- Live preview -->
        <div
            v-else
            class="space-y-12 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10 dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="mx-auto max-w-3xl space-y-4 text-center">
                <span
                    class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-300"
                >
                    Solusi Teknologi &amp; Software House Terpercaya
                </span>
                <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100">
                    {{ form.company_name || 'Nama Perusahaan' }}
                </h2>
                <p class="text-sm leading-relaxed text-slate-600 sm:text-base dark:text-slate-300">
                    {{ form.about }}
                </p>
                <div class="flex items-center justify-center gap-3 pt-2">
                    <a
                        v-if="whatsappLink"
                        :href="whatsappLink"
                        target="_blank"
                        rel="noreferrer"
                        class="flex items-center gap-2 rounded-xl bg-[#1e40af] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-blue-700 sm:text-sm"
                    >
                        <span class="material-symbols-outlined text-[18px]">chat</span>
                        <span>Konsultasi Proyek Gratis</span>
                    </a>
                </div>
            </div>

            <div class="space-y-6">
                <div class="mx-auto max-w-xl text-center">
                    <h3 class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Layanan Solusi Digital
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Kami merancang perangkat lunak yang disesuaikan dengan kebutuhan alur bisnis Anda.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="service in form.services"
                        :key="service.id"
                        class="space-y-2 rounded-2xl border border-slate-100 bg-slate-50/50 p-5 transition-all hover:bg-white hover:shadow-md dark:border-slate-800 dark:bg-slate-800/40 dark:hover:bg-slate-800"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                        >
                            <span class="material-symbols-outlined text-[22px]">code</span>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ service.name }}</h4>
                        <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ service.desc }}</p>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-3xl space-y-4">
                <h3 class="text-center text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Pertanyaan yang Sering Diajukan (FAQ)
                </h3>
                <div class="space-y-3">
                    <div
                        v-for="(faq, index) in form.faq"
                        :key="index"
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="text-xs font-bold text-slate-900 sm:text-sm dark:text-slate-100">
                            {{ faq.question }}
                        </div>
                        <div class="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                            {{ faq.answer }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="flex flex-col items-center justify-between gap-4 border-t border-slate-100 pt-8 text-xs text-slate-500 sm:flex-row dark:border-slate-800 dark:text-slate-400"
            >
                <div>{{ form.address }}</div>
                <div class="flex items-center gap-4">
                    <span>{{ form.email }}</span>
                    <span>&bull;</span>
                    <span>{{ form.phone }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
